<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use RuntimeException;

/**
 * The site isn't set up to sync yet: no pepper, bindings that can't be read, or no database.
 * The message says what to put right.
 *
 * @api
 */
final class NotReady extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
