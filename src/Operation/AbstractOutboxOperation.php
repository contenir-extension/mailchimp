<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Operation;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\OperationHandlerInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;

/**
 * An operation on the Sync screen that acts on every failed and dead message at once.
 *
 * @api
 */
abstract readonly class AbstractOutboxOperation implements OperationHandlerInterface
{
    /**
     * The most messages one press acts on.
     */
    public const int LIMIT = 500;

    public function __construct(
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * @throws StorageException When the outbox can't be read or written.
     */
    #[Override]
    final public function handle(OperationContextInterface $context): OperationResultInterface
    {
        $extension = $context->extension();
        if (null === $extension) {
            return OperationResult::message('danger', 'The CMS ran this without the Mailchimp add-on.');
        }

        try {
            $sync = $this->syncs->of($extension);
        } catch (NotReady $e) {
            return OperationResult::message('warning', $e->getMessage());
        }

        $count = 0;
        foreach ([OutboxState::Failed, OutboxState::Dead] as $state) {
            foreach ($sync->stores->outbox->inState($state, self::LIMIT) as $row) {
                $this->act($sync, $row->id);
                $count++;
            }
        }

        return OperationResult::message('success', $this->done($count));
    }

    /**
     * Does the operation to one message.
     *
     * @throws StorageException
     */
    abstract protected function act(Sync $sync, int $id): void;

    /**
     * What the operation did to $count messages.
     */
    abstract protected function done(int $count): string;
}
