<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Operation;

use Contenir\Extension\Mailchimp\Operation\TestConnection;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Http;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Operation\FakeOperationContext;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TestConnectionTest extends TestCase
{
    /**
     * @return list<array{level: string, text: string}>
     */
    private static function messages(FakeHttpClient $http, ArraySettings $settings): array
    {
        return (new TestConnection(Http::connections($http)))->handle(new FakeOperationContext(Contexts::make(
            $settings,
        )))
            ->messages();
    }

    #[Test]
    public function asksForTheKeyFirst(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'Enter the API key, and save, first.']],
            self::messages(new FakeHttpClient(), new ArraySettings()),
        );
    }

    #[Test]
    public function namesTheAccountAndItsAudiences(): void
    {
        static::assertSame(
            [['level' => 'success', 'text' => 'Connected to Sector7g. Its audiences: Newsletter, Test audience.']],
            self::messages(
                new FakeHttpClient(Replies::account(), Replies::lists(['l1' => 'Newsletter', 'l2' => 'Test audience'])),
                Settings::connected(),
            ),
        );
    }

    #[Test]
    public function saysWhenMailchimpRefusesTheKey(): void
    {
        static::assertSame(
            [[
                'level' => 'danger',
                'text'  => 'The service refused the connection: Your API key may be invalid, or you\'ve attempted to'
                    . ' access the wrong datacenter.',
            ]],
            self::messages(new FakeHttpClient(Replies::refused()), Settings::connected()),
        );
    }

    #[Test]
    public function saysWhenTheAccountHasNoAudiencesYet(): void
    {
        static::assertSame(
            [['level' => 'success', 'text' => 'Connected to Sector7g. It has no audiences yet.']],
            self::messages(new FakeHttpClient(Replies::account(), Replies::lists([])), Settings::connected()),
        );
    }

    #[Test]
    public function saysWhenTheCmsGivesNoAddOnContext(): void
    {
        static::assertSame(
            'danger',
            (new TestConnection())->handle(new FakeOperationContext(null))
                ->messages()[0]['level'],
        );
    }

    #[Test]
    public function saysWhenTheKeyNamesNoDataCentre(): void
    {
        static::assertSame(
            'warning',
            self::messages(new FakeHttpClient(), Settings::malformed())[0]['level'],
        );
    }
}
