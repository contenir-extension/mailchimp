<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Extension\Mailchimp\Service\Reply;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReplyTest extends TestCase
{
    /**
     * @return array<string, array{int, bool}>
     */
    public static function retryProvider(): array
    {
        return [
            'unreached'    => [0, true],
            'bad request'  => [400, false],
            'busy'         => [429, true],
            'just below'   => [499, false],
            'server error' => [500, true],
            'unavailable'  => [503, true],
        ];
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function successProvider(): array
    {
        return [
            'just below 200' => [199, false],
            '200'            => [200, true],
            '204'            => [204, true],
            '299'            => [299, true],
            '300'            => [300, false],
        ];
    }

    #[Test]
    public function givesMailchimpsDetail(): void
    {
        $reply = new Reply(400, ['title' => 'Invalid Resource', 'detail' => 'Please provide a valid email address.']);

        static::assertSame('Please provide a valid email address.', $reply->message());
    }

    #[Test]
    public function givesMailchimpsTitleWithoutADetail(): void
    {
        static::assertSame(
            'Resource Not Found',
            (new Reply(404, ['title' => 'Resource Not Found', 'detail' => '']))->message(),
        );
    }

    #[Test]
    public function givesTheStatusWhenThereIsNoMessage(): void
    {
        static::assertSame('Mailchimp answered 502', (new Reply(502))->message());
    }

    #[Test]
    public function givesWhyARequestNeverArrived(): void
    {
        static::assertSame('Timed out', (new Reply(0, failure: 'Timed out'))->message());
    }

    #[Test]
    #[DataProvider('retryProvider')]
    public function isWorthRetryingOnlyWhenBusyFailingOrUnreached(int $status, bool $retriable): void
    {
        static::assertSame($retriable, (new Reply($status))->retriable());
    }

    #[Test]
    public function readsMailchimpsNameForTheError(): void
    {
        static::assertSame(
            'Member In Compliance State',
            (new Reply(400, ['title' => 'Member In Compliance State']))->title(),
        );
    }

    #[Test]
    #[DataProvider('successProvider')]
    public function succeedsOnlyWithATwoHundredStatus(int $status, bool $ok): void
    {
        static::assertSame($ok, (new Reply($status))->ok());
    }
}
