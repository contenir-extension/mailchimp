<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Webhook;

use Contenir\Audience\Email;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Extension\Mailchimp\Webhook\Event;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EventTest extends TestCase
{
    /**
     * @return array<string, array{string, string, RemoteEventKind}>
     */
    public static function leavingProvider(): array
    {
        return [
            'unsubscribed'                => ['unsubscribe', 'manual', RemoteEventKind::Unsubscribed],
            'unsubscribed by a complaint' => ['unsubscribe', 'abuse', RemoteEventKind::Complaint],
            'cleaned after bouncing'      => ['cleaned', 'hard', RemoteEventKind::Cleaned],
            'cleaned after a complaint'   => ['cleaned', 'abuse', RemoteEventKind::Complaint],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function passedOverProvider(): array
    {
        $payload = [
            'type'     => 'unsubscribe',
            'fired_at' => '2026-11-04 09:30:00',
            'data'     => ['email' => 'ada@example.com'],
        ];

        return [
            'a subscribe'            => [['type' => 'subscribe'] + $payload],
            'a profile change'       => [['type' => 'profile'] + $payload],
            'no time'                => [['fired_at' => ''] + $payload],
            'a bad address'          => [['data' => ['email' => 'not an address']] + $payload],
            'no data'                => [['data' => 'x'] + $payload],
            'a new address the same' => [
                ['type' => 'upemail', 'data' => ['old_email' => 'ada@example.com', 'new_email' => 'ADA@example.com']]
                    + $payload,
            ],
            'no old address'         => [
                ['type' => 'upemail', 'data' => ['new_email' => 'ada@lovelace.example']] + $payload,
            ],
            'no new address'         => [
                ['type' => 'upemail', 'data' => ['old_email' => 'ada@example.com']] + $payload,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('passedOverProvider')]
    public function passesOverWhatTheSharedRulesNeedNotHear(array $payload): void
    {
        static::assertNull(Event::read($payload));
    }

    #[Test]
    public function readsAChangedAddress(): void
    {
        $event = Event::read([
            'type'     => 'upemail',
            'fired_at' => '2026-11-04 09:30:00',
            'data'     => [
                'old_email' => 'ada@example.com',
                'new_email' => 'ada@lovelace.example',
                'list_id'   => 'f1e2d3c4b5',
            ],
        ]);

        static::assertEquals(
            new RemoteEvent(
                RemoteEventKind::EmailChanged,
                Email::fromString('ada@example.com'),
                new DateTimeImmutable('2026-11-04 09:30:00 UTC'),
                Email::fromString('ada@lovelace.example'),
            ),
            $event,
        );
    }

    #[Test]
    #[DataProvider('leavingProvider')]
    public function readsWhySomeoneLeft(string $type, string $reason, RemoteEventKind $kind): void
    {
        $event = Event::read([
            'type'     => $type,
            'fired_at' => '2026-11-04 09:30:00',
            'data'     => ['reason' => $reason, 'email' => 'Ada@Example.com', 'list_id' => 'f1e2d3c4b5'],
        ]);

        static::assertEquals(
            new RemoteEvent(
                $kind,
                Email::fromString('ada@example.com'),
                new DateTimeImmutable('2026-11-04 09:30:00 UTC'),
            ),
            $event,
        );
    }
}
