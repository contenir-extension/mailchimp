<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Settings;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Mailchimp\MailchimpSection;
use Override;

use function array_key_exists;

/**
 * The Mailchimp section's settings held in an array: saved values over the declared
 * defaults, as a host reads them. Saving keeps the values, or refuses when told to.
 */
final class ArraySettings implements SettingsInterface
{
    /**
     * @param array<string, mixed> $values By field name
     */
    public function __construct(
        public array $values = [],
        private readonly ?string $refuse = null,
    ) {}

    /**
     * @return list<Field>
     */
    private static function fields(): array
    {
        return MailchimpSection::define(new Handle('mailchimp'))->form->fields;
    }

    #[Override]
    public function all(): array
    {
        $defaults = [];
        foreach (self::fields() as $field) {
            $defaults[$field->name] = $field->default;
        }

        return [...$defaults, ...$this->values];
    }

    #[Override]
    public function get(string $field, mixed $default = null): mixed
    {
        $all = $this->all();
        if (! array_key_exists($field, $all)) {
            throw UnknownSettingException::field(MailchimpSection::NAME, $field);
        }

        return $all[$field] ?? $default;
    }

    #[Override]
    public function section(): string
    {
        return MailchimpSection::NAME;
    }

    #[Override]
    public function set(array $values): ActionResult
    {
        if (null !== $this->refuse) {
            return new ActionResult(Outcome::Failed, null, $this->refuse);
        }

        $this->values = [...$this->values, ...$values];

        return new ActionResult(Outcome::Done, null);
    }
}
