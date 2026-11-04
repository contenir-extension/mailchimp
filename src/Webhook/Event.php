<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Webhook;

use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Extension\Mailchimp\Service\Json;
use DateTimeImmutable;

/**
 * What a webhook call reports, as the shared rules take it: an unsubscribe, a cleaned address,
 * either of them as a complaint when the reason is abuse, or a changed address. Anything else,
 * or anything incomplete, is passed over. Mailchimp's times are in UTC.
 *
 * @api
 */
final class Event
{
    /** The reason Mailchimp gives when someone marked an email as spam */
    public const string ABUSE = 'abuse';

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function read(array $payload): ?RemoteEvent
    {
        $data = Json::array($payload['data'] ?? null);
        $at   = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s e', Json::string($payload, 'fired_at') . ' UTC');
        if (false === $at) {
            return null;
        }

        return match (Json::string($payload, 'type')) {
            'unsubscribe' => self::left($data, $at, RemoteEventKind::Unsubscribed),
            'cleaned'     => self::left($data, $at, RemoteEventKind::Cleaned),
            'upemail'     => self::moved($data, $at),
            default       => null,
        };
    }

    private static function email(string $address): ?Email
    {
        try {
            return Email::fromString($address);
        } catch (InvalidEmail) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function left(array $data, DateTimeImmutable $at, RemoteEventKind $kind): ?RemoteEvent
    {
        $email = self::email(Json::string($data, 'email'));
        if (null === $email) {
            return null;
        }

        return new RemoteEvent(
            self::ABUSE === Json::string($data, 'reason') ? RemoteEventKind::Complaint : $kind,
            $email,
            $at,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function moved(array $data, DateTimeImmutable $at): ?RemoteEvent
    {
        $old = self::email(Json::string($data, 'old_email'));
        $new = self::email(Json::string($data, 'new_email'));

        return null === $old || null === $new || $old->value === $new->value
            ? null
            : new RemoteEvent(RemoteEventKind::EmailChanged, $old, $at, $new);
    }
}
