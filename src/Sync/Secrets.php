<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use Closure;
use Random\RandomException;

use function bin2hex;
use function random_bytes;

/**
 * Makes the add-on's secrets: the webhook's, and a pepper to offer. Each is 32 random bytes,
 * written as 64 hex digits.
 *
 * @api
 */
final readonly class Secrets
{
    public const int BYTES = 32;

    /**
     * @var Closure(int<1, max>): string
     */
    private Closure $random;

    /**
     * @param (Closure(int<1, max>): string)|null $random Gives that many random bytes; random_bytes() unless a test
     *                                                    gives another
     */
    public function __construct(?Closure $random = null)
    {
        $this->random = $random ?? random_bytes(...);
    }

    /**
     * @throws RandomException When the system has no source of randomness.
     */
    public function make(): string
    {
        return bin2hex(($this->random)(self::BYTES));
    }
}
