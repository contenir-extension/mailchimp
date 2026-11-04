<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Handler;

use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Mailchimp\Handler\SyncScreen;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function count;

#[Group('unit')]
final class SyncScreenTest extends TestCase
{
    private const string URL = '/admin/extension/mailchimp/sync';

    /**
     * @return array<string, array{array<string, mixed>|null}>
     */
    public static function unknownMessageProvider(): array
    {
        return [
            'nothing posted'    => [null],
            'no number'         => [['action' => 'retry']],
            'not a number'      => [['message' => 'two', 'action' => 'retry']],
            'no such message'   => [['message' => '99', 'action' => 'retry']],
            'a waiting message' => [['message' => '1', 'action' => 'remove']],
            'a sent message'    => [['message' => '4', 'action' => 'remove']],
        ];
    }

    /**
     * A kit whose outbox holds one waiting, one failed, one dead and one done message.
     */
    private static function kit(): SyncKit
    {
        $kit = new SyncKit();
        $now = $kit->clock->now();
        for ($n = 1; $n <= 4; $n++) {
            $kit->sync->audience()->update('newsletter', ['email' => "person{$n}@example.com"]);
        }

        $kit->stores->outbox->fail(2, 'Invalid email', $now);
        $kit->stores->outbox->dead(3, 'Timed out', $now->modify('+1 minute'));
        $kit->stores->outbox->done(4, $now);

        return $kit;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private static function post(SyncKit $kit, ?array $body): HandlerResult
    {
        return (new SyncScreen($kit->factory))->handle(
            (new ServerRequest('POST', self::URL))->withParsedBody($body),
            Contexts::make(),
        );
    }

    /**
     * @return list<string>
     */
    private static function states(SyncKit $kit): array
    {
        return array_values(array_map(
            static fn(OutboxRow $row): string => $row->state->value,
            $kit->stores->outbox->rows,
        ));
    }

    #[Test]
    public function asksForANumberEvenWhenTheFirstMessageFailed(): void
    {
        $kit = self::kit();
        $kit->stores->outbox->fail(1, 'Invalid email', $kit->clock->now());

        static::assertSame(
            ['warning', 'failed'],
            [
                self::post($kit, ['action' => 'remove'])->messages[0]['level'],
                $kit->stores->outbox->rows[1]->state->value,
            ],
        );
    }

    /**
     * @param array<string, mixed>|null $body
     */
    #[DataProvider('unknownMessageProvider')]
    #[Test]
    public function asksForTheNumberOfAFailedOrDeadMessage(?array $body): void
    {
        $kit    = self::kit();
        $result = self::post($kit, $body);

        static::assertSame(
            [
                [['level' => 'warning', 'text' => 'Enter the number of a failed or dead message in the table.']],
                ['waiting', 'failed', 'dead', 'done'],
            ],
            [$result->messages, self::states($kit)],
        );
    }

    #[Test]
    public function asksWhichMessageAndWhatToDoWithIt(): void
    {
        $result = (new SyncScreen(self::kit()->factory))->handle(new ServerRequest('GET', self::URL), Contexts::make());

        static::assertSame(
            [
                ['message', 'number', null, []],
                ['action', 'radio', 'retry', ['retry' => 'Retry it', 'remove' => 'Remove it']],
            ],
            array_map(
                static fn($field): array => [$field->name, $field->type, $field->default, $field->options],
                $result->screen->form->fields ?? [],
            ),
        );
    }

    #[Test]
    public function changesNothingWhenOnlyLookedAt(): void
    {
        $kit    = self::kit();
        $result = (new SyncScreen($kit->factory))->handle(
            (new ServerRequest('GET', self::URL))->withParsedBody(['message' => '2', 'action' => 'remove']),
            Contexts::make(),
        );

        static::assertSame([[], ['waiting', 'failed', 'dead', 'done']], [$result->messages, self::states($kit)]);
    }

    #[Test]
    public function countsTheMessagesInEachState(): void
    {
        $result = (new SyncScreen(self::kit()->factory))->handle(new ServerRequest('GET', self::URL), Contexts::make());

        static::assertSame(
            'Waiting: 1. Sent: 1. Failed: 1. Ran out of retries: 1. Sent messages are kept for 7 days, failed ones'
                . ' for 30.',
            $result->screen?->intro,
        );
    }

    #[Test]
    public function listsAtMostFiftyMessagesOfEachState(): void
    {
        $kit = new SyncKit();
        for ($n = 1; $n <= 51; $n++) {
            $kit->sync->audience()->update('newsletter', ['email' => "person{$n}@example.com"]);
        }

        $result = (new SyncScreen($kit->factory))->handle(new ServerRequest('GET', self::URL), Contexts::make());

        static::assertCount(50, $result->screen->table->rows ?? []);
    }

    #[Test]
    public function listsDeadThenFailedThenWaitingMessagesWithTheirLastError(): void
    {
        $result = (new SyncScreen(self::kit()->factory))->handle(new ServerRequest('GET', self::URL), Contexts::make());

        static::assertSame(
            [
                [
                    'id'       => 3,
                    'binding'  => 'newsletter',
                    'action'   => 'update',
                    'email'    => 'person3@example.com',
                    'state'    => 'dead',
                    'attempts' => 0,
                    'error'    => 'Timed out',
                    'changed'  => '5 Nov 2026 09:01',
                ],
                [2, 'failed', 'Invalid email'],
                [1, 'waiting', null],
            ],
            [
                $result->screen?->table?->rows[0],
                [
                    $result->screen?->table?->rows[1]['id'],
                    $result->screen?->table?->rows[1]['state'],
                    $result->screen?->table?->rows[1]['error'],
                ],
                [
                    $result->screen?->table?->rows[2]['id'],
                    $result->screen?->table?->rows[2]['state'],
                    $result->screen?->table?->rows[2]['error'],
                ],
            ],
        );
    }

    #[Test]
    public function offersToRetryOrRemoveEveryFailedAndDeadMessage(): void
    {
        $result = (new SyncScreen(self::kit()->factory))->handle(new ServerRequest('GET', self::URL), Contexts::make());

        static::assertSame(
            [
                ['retry-failed',  'update', false, false],
                ['remove-failed', 'delete', true,  true],
            ],
            array_map(
                static fn(Operation $operation): array => [
                    $operation->name,
                    $operation->privilege,
                    $operation->confirm,
                    $operation->destructive,
                ],
                $result->screen->operations ?? [],
            ),
        );
    }

    #[Test]
    public function removesTheMessageItIsGiven(): void
    {
        $kit    = self::kit();
        $result = self::post($kit, ['message' => '3', 'action' => 'remove']);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Removed message 3.']], ['waiting', 'failed', 'done']],
            [$result->messages, self::states($kit)],
        );
    }

    #[Test]
    public function retriesTheMessageItIsGiven(): void
    {
        $kit    = self::kit();
        $result = self::post($kit, ['message' => '2', 'action' => 'retry']);

        static::assertSame(
            [
                [['level' => 'success', 'text' => 'Message 2 is back to wait, and goes in the next minute.']],
                ['waiting', 'waiting', 'dead', 'done'],
                3,
            ],
            [$result->messages, self::states($kit), count($result->screen->table->rows ?? [])],
        );
    }

    #[Test]
    public function saysWhatTheSiteStillNeeds(): void
    {
        $result = (new SyncScreen(FakeSyncFactory::notReady('No pepper.')))->handle(
            new ServerRequest('GET', self::URL),
            Contexts::make(),
        );

        static::assertSame(['Sync', 'No pepper.', null], [
            $result->screen?->title,
            $result->screen?->intro,
            $result->screen?->table,
        ]);
    }
}
