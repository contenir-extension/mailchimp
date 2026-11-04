<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\PersonStatus;
use DateTimeInterface;

use function array_map;
use function md5;

/**
 * A person as Mailchimp takes them: their address, merge fields, and, when they join, how and
 * with what consent. Someone already in the audience keeps their status unless they agreed
 * afresh.
 *
 * @api
 */
final class Member
{
    /**
     * The merge fields: Mailchimp takes text and numbers, so yes or no is sent as text, and an
     * empty value clears the field.
     *
     * @return array{merge_fields: array<array-key, string|int|float>}
     */
    public static function fields(Person $person): array
    {
        return ['merge_fields' => array_map(
            static fn(string|int|float|bool|null $value): string|int|float => match (true) {
                true === $value => 'Yes',
                false === $value => 'No',
                null === $value => '',
                default => $value,
            },
            $person->values,
        )];
    }

    /**
     * Mailchimp's id for a member: the MD5 of their lower-cased address, which Email already is.
     */
    public static function hash(Person $person): string
    {
        return md5($person->email->value);
    }

    /**
     * Adds or updates a person: status_if_new says how they join, and status is sent only for
     * fresh consent, so someone who left isn't taken back without it.
     *
     * @return array<string, mixed>
     */
    public static function subscribe(Person $person): array
    {
        $status = PersonStatus::Pending === $person->status ? 'pending' : 'subscribed';
        $body   = [
            'email_address' => $person->email->value,
            'status_if_new' => $status,
            ...self::fields($person),
            ...self::signup($person),
        ];

        return $person->resubscribe ? [...$body, 'status' => $status] : $body;
    }

    /**
     * When and from where they signed up, as their consent records it.
     *
     * @return array<string, string>
     */
    private static function signup(Person $person): array
    {
        if (null === $person->consent) {
            return [];
        }

        $signup = ['timestamp_signup' => $person->consent->at->format(DateTimeInterface::ATOM)];

        return null === $person->consent->ip ? $signup : [...$signup, 'ip_signup' => $person->consent->ip];
    }
}
