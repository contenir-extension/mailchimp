<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Listener;

use Contenir\Audience\Audience;
use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Extension\Api\Event\Change;
use DateTimeImmutable;

use function in_array;
use function is_string;
use function trim;

/**
 * What one saved record means for a binding that syncs its section. A binding names the
 * record's consent checkbox with the option "consent_field"; without one, being in the section
 * is the consent. Ticking it, or a new record or address while it's ticked, is fresh consent and
 * subscribes with the binding's wording; unticking it, or a changed address, removes the old
 * address; any other save only updates someone already on the list.
 *
 * @api
 */
final readonly class RecordChange
{
    /**
     * The binding option naming the record's consent checkbox.
     */
    public const string CONSENT_FIELD = 'consent_field';

    public function __construct(
        private Binding $binding,
        private Change $change,
    ) {}

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private static function yes(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'Yes', 'on', 'true'], strict: true);
    }

    /**
     * Puts what the save means in the outbox.
     *
     * @throws InvalidEmail     When the record's address isn't one.
     * @throws StorageException When the outbox can't be written.
     * @throws UnknownBinding   Never: the binding is the audience's own.
     */
    public function queue(Audience $audience, DateTimeImmutable $now): void
    {
        $email  = self::text($this->change->after[$this->binding->emailField] ?? null);
        $old    = self::text($this->change->before[$this->binding->emailField] ?? null);
        $moved  = '' !== $old && $old !== $email;
        $ticked = $this->ticked($this->change->after);
        $was    = ! $moved && '' !== $old && $this->ticked($this->change->before);
        if ($moved) {
            $audience->remove($this->binding->name, $old);
        }

        if ('' === $email || (! $ticked && ! $was)) {
            return;
        }

        if (! $ticked) {
            $audience->remove($this->binding->name, $email);

            return;
        }

        if ($was || null === $this->binding->consent) {
            $audience->update($this->binding->name, $this->change->after);

            return;
        }

        $audience->subscribe(
            $this->binding->name,
            $this->change->after,
            new Consent($this->binding->consent, ConsentSource::Record, $now),
        );
    }

    /**
     * Whether $values hold the binding's consent: its checkbox ticked, or, with none, any record.
     *
     * @param array<string, mixed> $values
     */
    private function ticked(array $values): bool
    {
        $field = $this->binding->option(self::CONSENT_FIELD);

        return is_string($field) ? self::yes($values[$field] ?? null) : true;
    }
}
