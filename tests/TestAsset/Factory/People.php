<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Factory;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Consent;
use Contenir\Audience\Email;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\PersonStatus;

use function md5;

/**
 * Bindings and people as the shared rules hand them to the adapter.
 */
final class People
{
    public const string LIST = 'f1e2d3c4b5';

    /**
     * @param list<string>                         $tags
     * @param array<string, string|int|float|bool> $options
     */
    public static function binding(array $tags = [], array $options = []): Binding
    {
        return new Binding('newsletter', self::LIST, tags: $tags, options: $options);
    }

    public static function hash(string $email = 'ada@example.com'): string
    {
        return md5($email);
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public static function person(
        string $email = 'ada@example.com',
        array $values = [],
        PersonStatus $status = PersonStatus::Subscribed,
        ?Consent $consent = null,
        bool $resubscribe = false,
    ): Person {
        return new Person(Email::fromString($email), $values, $status, $consent, $resubscribe);
    }
}
