<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Mailchimp\Service\Connection;
use SensitiveParameter;

use function is_string;
use function trim;

/**
 * The Mailchimp section's saved values, read once: the API key, whose suffix names the data
 * centre, and the secret in the webhook's address. Blank reads as not set.
 *
 * @api
 */
final readonly class MailchimpSettings
{
    public function __construct(
        #[SensitiveParameter]
        public ?string $apiKey,
        #[SensitiveParameter]
        public ?string $webhookSecret,
    ) {}

    /**
     * @throws UnknownSettingException Never: every field read is declared.
     */
    public static function from(SettingsInterface $settings): self
    {
        return new self(
            self::text($settings->get(MailchimpSection::API_KEY)),
            self::text($settings->get(MailchimpSection::WEBHOOK_SECRET)),
        );
    }

    private static function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return '' === $text ? null : $text;
    }

    /**
     * The connection to the key's data centre, or null until a key that names one is saved.
     */
    public function connection(): ?Connection
    {
        if (null === $this->apiKey) {
            return null;
        }

        try {
            return new Connection($this->apiKey);
        } catch (ConnectionRefused) {
            return null;
        }
    }

    /**
     * What still has to be entered before the add-on can reach Mailchimp, or null when nothing
     * does.
     */
    public function missing(): ?string
    {
        return match (true) {
            null === $this->apiKey => 'Enter the API key, and save, first.',
            null === $this->connection() => 'That isn\'t a Mailchimp API key: it ends with its data centre, such as'
                . ' -us21. Copy it again from Mailchimp, and save.',
            default => null,
        };
    }
}
