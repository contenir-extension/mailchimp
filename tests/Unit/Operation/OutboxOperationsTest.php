<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Operation;

use Contenir\Extension\Mailchimp\Operation\RemoveFailed;
use Contenir\Extension\Mailchimp\Operation\RetryFailed;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Operation\FakeOperationContext;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;

/**
 * Retry failed and dead, and Remove failed and dead, on the Sync screen.
 */
#[Group('unit')]
final class OutboxOperationsTest extends TestCase
{
    /**
     * A kit whose outbox holds one waiting, one failed, one dead and one done message.
     */
    private static function kit(): SyncKit
    {
        $kit    = new SyncKit();
        $outbox = $kit->stores->outbox;
        $now    = $kit->clock->now();
        for ($n = 1; $n <= 4; $n++) {
            $kit->sync->audience()->update('newsletter', ['email' => "person{$n}@example.com"]);
        }

        $outbox->fail(2, 'Invalid email', $now);
        $outbox->dead(3, 'Timed out', $now);
        $outbox->done(4, $now);

        return $kit;
    }

    /**
     * @return list<string> Each message's state, in order
     */
    private static function states(SyncKit $kit): array
    {
        return array_values(array_map(
            static fn($row): string => $row->state->value,
            $kit->stores->outbox->rows,
        ));
    }

    #[Test]
    public function removesEveryFailedAndDeadMessage(): void
    {
        $kit = self::kit();

        $result = (new RemoveFailed($kit->factory))->handle(new FakeOperationContext(Contexts::make()));

        static::assertSame(
            [[['level' => 'success', 'text' => 'Removed 2 messages.']], ['waiting', 'done']],
            [$result->messages(), self::states($kit)],
        );
    }

    #[Test]
    public function retriesEveryFailedAndDeadMessage(): void
    {
        $kit = self::kit();

        $result = (new RetryFailed($kit->factory))->handle(new FakeOperationContext(Contexts::make()));

        static::assertSame(
            [
                [['level' => 'success', 'text' => '2 messages are back to wait, and go in the next minute.']],
                ['waiting', 'waiting', 'waiting', 'done'],
            ],
            [$result->messages(), self::states($kit)],
        );
    }

    #[Test]
    public function saysWhatTheSiteStillNeeds(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'No pepper.']],
            (new RetryFailed(FakeSyncFactory::notReady('No pepper.')))->handle(
                new FakeOperationContext(Contexts::make()),
            )
                ->messages(),
        );
    }

    #[Test]
    public function saysWhenTheCmsGivesNoAddOnContext(): void
    {
        static::assertSame(
            'danger',
            (new RemoveFailed())->handle(new FakeOperationContext(null))
                ->messages()[0]['level'],
        );
    }

    #[Test]
    public function speaksOfOneMessageAsOne(): void
    {
        $kit = new SyncKit();
        $kit->sync->audience()->update('newsletter', ['email' => 'ada@example.com']);
        $kit->stores->outbox->fail(1, 'Invalid email', $kit->clock->now());
        $retried = (new RetryFailed($kit->factory))->handle(new FakeOperationContext(Contexts::make()));
        $kit->stores->outbox->fail(1, 'Invalid email', $kit->clock->now());
        $removed = (new RemoveFailed($kit->factory))->handle(new FakeOperationContext(Contexts::make()));

        static::assertSame(
            ['1 message is back to wait, and goes in the next minute.', 'Removed 1 message.'],
            [$retried->messages()[0]['text'], $removed->messages()[0]['text']],
        );
    }
}
