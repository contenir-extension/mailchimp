<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Check;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;

use function sprintf;

/**
 * Whether any message failed or ran out of retries, and so waits for someone to retry or remove
 * it on the Sync screen.
 *
 * @api
 */
final readonly class OutboxCheck implements CheckInterface
{
    public const string NAME = 'outbox';

    public function __construct(
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * @throws StorageException When the outbox can't be read.
     *
     * @mago-expect analysis:possibly-undefined-string-array-index,possibly-null-operand The outbox counts every state, with 0 for none.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        try {
            $counts = $this->syncs->of($context)->stores->outbox->counts();
        } catch (NotReady) {
            return CheckResult::skipped('The site isn\'t set up to sync yet.');
        }

        $stuck   = $counts[OutboxState::Failed->value] + $counts[OutboxState::Dead->value];
        $waiting = $counts[OutboxState::Waiting->value];

        return 0 === $stuck
            ? CheckResult::success(sprintf('%d waiting to be sent.', $waiting))
            : CheckResult::warning(sprintf(
                '%d %s failed or ran out of retries. Retry or remove %s on the Sync screen.',
                $stuck,
                1 === $stuck ? 'message' : 'messages',
                1 === $stuck ? 'it' : 'them',
            ));
    }

    #[Override]
    public function label(): string
    {
        return 'Mailchimp outbox';
    }
}
