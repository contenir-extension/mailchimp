<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Schedule;

use Contenir\Audience\Exception\StorageException;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Schedule\ScheduledTaskInterface;
use Contenir\Extension\Mailchimp\Connections;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;

/**
 * drain, every minute: sends what's due in the outbox for up to 40 seconds, then deletes
 * messages kept past their time. The job runner skips a run while the last is still going.
 * Until the connection is saved and the site set up to sync, it does nothing: the update check
 * says what's missing.
 *
 * @api
 */
final readonly class Drain implements ScheduledTaskInterface
{
    public const string NAME = 'drain';

    public function __construct(
        private Connections $connections = new Connections(),
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * @throws StorageException When the outbox can't be read or written, which fails the run.
     */
    #[Override]
    public function run(ExtensionContextInterface $context): void
    {
        $connection = MailchimpSettings::from($context->settings())->connection();
        if (null === $connection) {
            return;
        }

        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady) {
            return;
        }

        $worker = $sync->worker();
        $report = $worker->drain($this->connections->adapter($connection));
        if (DrainSummary::noteworthy($report)) {
            $context->logger()->info(DrainSummary::text($report));
        }

        $pruned = $worker->prune();
        if ($pruned > 0) {
            $context->logger()->info('Deleted {count} messages kept past their time', ['count' => $pruned]);
        }
    }
}
