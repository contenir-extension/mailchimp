<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

use JsonException;

use function array_filter;
use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Typed reads from Mailchimp's JSON, where anything may be missing.
 *
 * @api
 */
final class Json
{
    /**
     * An object or array, or an empty one in place of anything else.
     *
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    public static function bool(mixed $row, string $key): bool
    {
        return is_array($row) && array_key_exists($key, $row) && true === $row[$key];
    }

    /**
     * The JSON object or array in $json, or null when it holds something else or isn't JSON.
     *
     * @return array<array-key, mixed>|null
     */
    public static function document(string $json): ?array
    {
        try {
            return self::nullable(json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * The objects in a JSON array, skipping anything else in it.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    public static function string(mixed $row, string $key): string
    {
        return is_array($row) && array_key_exists($key, $row) && is_string($row[$key]) ? $row[$key] : '';
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function nullable(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }
}
