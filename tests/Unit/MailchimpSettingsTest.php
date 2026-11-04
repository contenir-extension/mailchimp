<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit;

use Contenir\Extension\Mailchimp\MailchimpSection;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Contenir\Extension\Mailchimp\Service\Connection;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MailchimpSettingsTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string|null}>
     */
    public static function missingProvider(): array
    {
        return [
            'nothing'        => [[], 'Enter the API key, and save, first.'],
            'a blank key'    => [[MailchimpSection::API_KEY => '  '], 'Enter the API key, and save, first.'],
            'no data centre' => [
                [MailchimpSection::API_KEY => 'not-a-key'],
                'That isn\'t a Mailchimp API key: it ends with its data centre, such as -us21. Copy it again from'
                    . ' Mailchimp, and save.',
            ],
            'a key'          => [[MailchimpSection::API_KEY => Apis::KEY], null],
        ];
    }

    #[Test]
    public function connectsToTheKeysDataCentre(): void
    {
        $settings = MailchimpSettings::from(new ArraySettings([MailchimpSection::API_KEY => ' ' . Apis::KEY . ' ']));

        static::assertEquals(new Connection(Apis::KEY), $settings->connection());
    }

    #[Test]
    public function hasNoConnectionWithAKeyThatNamesNoDataCentre(): void
    {
        static::assertNull(
            MailchimpSettings::from(new ArraySettings([
                MailchimpSection::API_KEY => 'not-a-key',
            ]))->connection(),
        );
    }

    #[Test]
    public function hasNoConnectionWithoutAKey(): void
    {
        static::assertNull(MailchimpSettings::from(new ArraySettings())->connection());
    }

    #[Test]
    public function readsAValueThatIsNotTextAsNotSet(): void
    {
        static::assertNull(MailchimpSettings::from(new ArraySettings([
            MailchimpSection::API_KEY => 12,
        ]))->apiKey);
    }

    #[Test]
    public function readsTheWebhookSecret(): void
    {
        static::assertSame(
            's3cret',
            MailchimpSettings::from(new ArraySettings([
                MailchimpSection::WEBHOOK_SECRET => 's3cret',
            ]))->webhookSecret,
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('missingProvider')]
    #[Test]
    public function saysWhatIsStillToEnter(array $values, ?string $missing): void
    {
        static::assertSame($missing, MailchimpSettings::from(new ArraySettings($values))->missing());
    }
}
