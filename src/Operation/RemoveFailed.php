<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Operation;

use Contenir\Extension\Mailchimp\Sync\Sync;
use Override;

use function sprintf;

/**
 * Remove failed and dead: deletes each, so nothing more is tried for them.
 *
 * @api
 */
final readonly class RemoveFailed extends AbstractOutboxOperation
{
    public const string NAME = 'remove-failed';

    #[Override]
    protected function act(Sync $sync, int $id): void
    {
        $sync->stores->outbox->delete($id);
    }

    #[Override]
    protected function done(int $count): string
    {
        return sprintf('Removed %d %s.', $count, 1 === $count ? 'message' : 'messages');
    }
}
