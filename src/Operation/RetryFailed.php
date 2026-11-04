<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Operation;

use Contenir\Extension\Mailchimp\Sync\Sync;
use Override;

use function sprintf;

/**
 * Retry failed and dead: puts each back to wait, due at once with a full set of retries.
 *
 * @api
 */
final readonly class RetryFailed extends AbstractOutboxOperation
{
    public const string NAME = 'retry-failed';

    #[Override]
    protected function act(Sync $sync, int $id): void
    {
        $sync->stores->outbox->requeue($id, $sync->clock->now());
    }

    #[Override]
    protected function done(int $count): string
    {
        return 1 === $count
            ? '1 message is back to wait, and goes in the next minute.'
            : sprintf('%d messages are back to wait, and go in the next minute.', $count);
    }
}
