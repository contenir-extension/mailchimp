<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Factory;

use Contenir\Extension\Mailchimp\MailchimpSection;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use SensitiveParameter;

/**
 * The section's settings at each stage of setting the add-on up.
 */
final class Settings
{
    /**
     * @mago-expect lint:no-literal-password A webhook secret made up for the tests.
     */
    public const string SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * A signing secret made up for the tests.
     */
    public const string SIGNING = 'signed by Mailchimp in the tests';

    /**
     * Another signing secret made up for the tests.
     */
    public const string OTHER_SIGNING = 'signed again by Mailchimp in the tests';

    public static function connected(
        #[SensitiveParameter]
        ?string $secret = self::SECRET,
    ): ArraySettings {
        return new ArraySettings([
            MailchimpSection::API_KEY        => Apis::KEY,
            MailchimpSection::WEBHOOK_SECRET => $secret,
        ]);
    }

    /**
     * A key without the data centre a Mailchimp key ends with.
     */
    public static function malformed(): ArraySettings
    {
        return new ArraySettings([MailchimpSection::API_KEY => 'not-a-key']);
    }
}
