<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Mailchimp\Check\ConnectionCheck;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Http;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionCheckTest extends TestCase
{
    #[Test]
    public function failsWithAWayToTheSettingsWhileTheKeyIsRefused(): void
    {
        $result = (new ConnectionCheck(Http::connections(new FakeHttpClient(Replies::refused()))))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [
                Status::Failure,
                'The service refused the connection: Your API key may be invalid, or you\'ve attempted to access the'
                    . ' wrong datacenter. Nothing is sent until it\'s put right; everything waits.',
                'Open the connection settings',
                'Check the API key in Site Settings > Integration > Mailchimp, save, and press Test connection.'
                    . ' Sending starts again in the next minute.',
            ],
            [
                $result->status,
                $result->message,
                $result->fix instanceof GuidedFix ? $result->fix->label : null,
                $result->fix instanceof GuidedFix ? $result->fix->explanation : null,
            ],
        );
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Mailchimp connection', (new ConnectionCheck())->label());
    }

    #[Test]
    public function namesTheAccountItReaches(): void
    {
        $result = (new ConnectionCheck(Http::connections(new FakeHttpClient(Replies::account()))))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame([Status::Success, 'Connected to Sector7g.'], [$result->status, $result->message]);
    }

    #[Test]
    public function warnsUntilTheConnectionIsSaved(): void
    {
        $result = (new ConnectionCheck())->check(Contexts::make(new ArraySettings()));

        static::assertSame(
            [Status::Warning, 'Enter the API key, and save, first.'],
            [$result->status, $result->message],
        );
    }
}
