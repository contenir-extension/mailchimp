<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Operation;

use Contenir\Audience\Exception\StorageException;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Contenir\Extension\Mailchimp\Schedule\DrainSummary;
use Contenir\Extension\Mailchimp\Service\Connection;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Override;

/**
 * Sync all now: sends what's waiting in the outbox at once, rather than in the next minute, and
 * says what became of it.
 *
 * @api
 */
final class SyncNow extends AbstractMailchimpOperation
{
    public const string NAME = 'sync-now';

    /**
     * @throws StorageException When the outbox can't be read or written.
     */
    #[Override]
    protected function run(
        Connection $connection,
        Sync $sync,
        MailchimpSettings $settings,
        ExtensionContextInterface $context,
    ): OperationResultInterface {
        $report = $sync->worker()->drain($this->connections->adapter($connection));

        return OperationResult::message(DrainSummary::level($report), DrainSummary::text($report));
    }
}
