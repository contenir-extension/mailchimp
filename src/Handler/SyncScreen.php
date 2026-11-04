<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Handler;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Worker\OutboxWorker;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Api\Handler\Table;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Mailchimp\Operation\RemoveFailed;
use Contenir\Extension\Mailchimp\Operation\RetryFailed;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function is_array;
use function is_numeric;
use function sprintf;

/**
 * Sync: how many messages wait, were sent, failed or ran out of retries, and the ones that need
 * looking at, with their last error. Failed and dead messages can be retried or removed, all at
 * once or one by its number.
 *
 * @api
 */
final readonly class SyncScreen implements HandlerInterface
{
    public const string NAME = 'sync';

    public const string MESSAGE = 'message';

    public const string ACTION = 'action';

    /**
     * How many messages of each state the screen lists.
     */
    public const int SHOWN = 50;

    private const string RETRY = 'retry';

    private const string REMOVE = 'remove';

    public function __construct(
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * Retries or removes the message the form names.
     *
     * @return array{string, string} The message's level and text
     *
     * @throws StorageException
     */
    private static function act(Sync $sync, ServerRequestInterface $request): array
    {
        $body   = $request->getParsedBody();
        $posted = is_array($body) ? $body : [];
        $id     = is_numeric($posted[self::MESSAGE] ?? null) ? (int) $posted[self::MESSAGE] : null;
        $row    = null === $id ? null : $sync->stores->outbox->find($id);
        if (
            null === $id
            || null === $row
            || ! in_array($row->state, [OutboxState::Failed, OutboxState::Dead], strict: true)
        ) {
            return ['warning', 'Enter the number of a failed or dead message in the table.'];
        }

        if (self::REMOVE === ($posted[self::ACTION] ?? null)) {
            $sync->stores->outbox->delete($id);

            return ['success', sprintf('Removed message %d.', $id)];
        }

        $sync->stores->outbox->requeue($id, $sync->clock->now());

        return ['success', sprintf('Message %d is back to wait, and goes in the next minute.', $id)];
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function row(OutboxRow $row): array
    {
        return [
            'id'       => $row->id,
            'binding'  => $row->binding,
            'action'   => $row->action->value,
            'email'    => $row->email?->value,
            'state'    => $row->state->value,
            'attempts' => $row->attempts,
            'error'    => $row->lastError,
            'changed'  => $row->updatedAt->format('j M Y H:i'),
        ];
    }

    /**
     * @throws StorageException
     *
     * @mago-expect analysis:possibly-undefined-string-array-index The outbox counts every state, with 0 for none.
     */
    private static function screen(Sync $sync): Screen
    {
        $counts = $sync->stores->outbox->counts();
        $rows   = [];
        foreach ([OutboxState::Dead, OutboxState::Failed, OutboxState::Waiting] as $state) {
            foreach ($sync->stores->outbox->inState($state, self::SHOWN) as $row) {
                $rows[] = self::row($row);
            }
        }

        return new Screen(
            title: 'Sync',
            intro: sprintf(
                'Waiting: %d. Sent: %d. Failed: %d. Ran out of retries: %d. Sent messages are kept for %d days,'
                    . ' failed ones for %d.',
                $counts[OutboxState::Waiting->value],
                $counts[OutboxState::Done->value],
                $counts[OutboxState::Failed->value],
                $counts[OutboxState::Dead->value],
                OutboxWorker::KEEP_DONE_DAYS,
                OutboxWorker::KEEP_FAILED_DAYS,
            ),
            form: new Form([
                new Field(
                    name: self::MESSAGE,
                    label: 'Message',
                    type: FieldType::Number,
                    description: 'The number of a failed or dead message in the table.',
                ),
                new Field(
                    name: self::ACTION,
                    label: 'What to do',
                    type: FieldType::Radio,
                    default: self::RETRY,
                    options: [self::RETRY => 'Retry it', self::REMOVE => 'Remove it'],
                ),
            ]),
            table: new Table(
                [
                    'id'       => 'Message',
                    'binding'  => 'Binding',
                    'action'   => 'Action',
                    'email'    => 'Email',
                    'state'    => 'State',
                    'attempts' => 'Attempts',
                    'error'    => 'Last error',
                    'changed'  => 'Changed',
                ],
                $rows,
            ),
            operations: [
                new Operation(
                    name: RetryFailed::NAME,
                    label: 'Retry failed and dead',
                    icon: 'refresh',
                    privilege: 'update',
                    handler: RetryFailed::class,
                ),
                new Operation(
                    name: RemoveFailed::NAME,
                    label: 'Remove failed and dead',
                    icon: 'trash',
                    privilege: 'delete',
                    handler: RemoveFailed::class,
                    confirm: true,
                    confirmMessage: 'Remove every failed and dead message? Nothing more is tried for them.',
                    destructive: true,
                ),
            ],
        );
    }

    /**
     * @throws StorageException When the outbox can't be read or written.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady $e) {
            return HandlerResult::screen(new Screen(
                title: 'Sync',
                intro: $e->getMessage(),
            ));
        }

        if ('POST' !== $request->getMethod()) {
            return HandlerResult::screen(self::screen($sync));
        }

        [$level, $text] = self::act($sync, $request);

        return HandlerResult::screen(self::screen($sync))->withMessage($level, $text);
    }
}
