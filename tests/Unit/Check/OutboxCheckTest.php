<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Mailchimp\Check\OutboxCheck;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OutboxCheckTest extends TestCase
{
    private static function kit(int $messages): SyncKit
    {
        $kit = new SyncKit();
        for ($n = 1; $n <= $messages; $n++) {
            $kit->sync->audience()->update('newsletter', ['email' => "person{$n}@example.com"]);
        }

        return $kit;
    }

    #[Test]
    public function countsWhatIsWaitingWhenNothingIsStuck(): void
    {
        $result = (new OutboxCheck(self::kit(2)->factory))->check(Contexts::make());

        static::assertSame([Status::Success, '2 waiting to be sent.'], [$result->status, $result->message]);
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Mailchimp outbox', (new OutboxCheck())->label());
    }

    #[Test]
    public function isSkippedUntilTheSiteIsSetUp(): void
    {
        static::assertSame(
            Status::Skipped,
            (new OutboxCheck(FakeSyncFactory::notReady()))->check(Contexts::make())->status,
        );
    }

    #[Test]
    public function warnsOfFailedAndDeadMessages(): void
    {
        $kit = self::kit(3);
        $kit->stores->outbox->fail(1, 'Invalid email', $kit->clock->now());
        $kit->stores->outbox->dead(2, 'Timed out', $kit->clock->now());

        $result = (new OutboxCheck($kit->factory))->check(Contexts::make());

        static::assertSame(
            [Status::Warning, '2 messages failed or ran out of retries. Retry or remove them on the Sync screen.'],
            [$result->status, $result->message],
        );
    }

    #[Test]
    public function warnsOfOneStuckMessageAsOne(): void
    {
        $kit = self::kit(1);
        $kit->stores->outbox->dead(1, 'Timed out', $kit->clock->now());

        static::assertSame(
            '1 message failed or ran out of retries. Retry or remove it on the Sync screen.',
            (new OutboxCheck($kit->factory))->check(Contexts::make())->message,
        );
    }
}
