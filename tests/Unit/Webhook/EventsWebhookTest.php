<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Webhook;

use Contenir\Audience\Email;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Handler\WebhookResponse;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Http;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use Contenir\Extension\Mailchimp\Webhook\EventsWebhook;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hash_hmac;
use function http_build_query;

#[Group('unit')]
final class EventsWebhookTest extends TestCase
{
    private const string URL = 'https://www.example.com/api/extension/mailchimp/events';

    /** @var array<string, mixed> */
    private const array UNSUBSCRIBE = [
        'type'     => 'unsubscribe',
        'fired_at' => '2026-11-05 09:00:00',
        'data'     => ['action' => 'unsub', 'email' => 'ada@example.com', 'list_id' => People::LIST],
    ];

    /**
     * @return array<string, array{array<string, string>, ArraySettings}>
     */
    public static function unverifiedProvider(): array
    {
        return [
            'no key'              => [['binding' => 'newsletter'], Settings::connected()],
            'an empty key'        => [['binding' => 'newsletter', 'key' => ''], Settings::connected()],
            'the wrong key'       => [['binding' => 'newsletter', 'key' => 'guess'], Settings::connected()],
            'no secret saved'     => [['binding' => 'newsletter', 'key' => ''], Settings::connected(secret: null)],
            'a key but no secret' => [['binding' => 'newsletter', 'key' => 'guess'], Settings::connected(secret: null)],
        ];
    }

    /**
     * @return array{int, string}
     */
    private static function answer(WebhookResponse $response): array
    {
        return [$response->status, $response->body];
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>  $body
     */
    private static function call(
        array $query = ['binding' => 'newsletter', 'key' => Settings::SECRET],
        array $body = [],
    ): ServerRequest {
        return (new ServerRequest(
            'POST',
            self::URL,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($body),
        ))->withQueryParams($query);
    }

    #[Test]
    public function acceptsACallCarryingTheSecret(): void
    {
        static::assertTrue((new EventsWebhook())->verify(self::call(), Contexts::make(Settings::connected())));
    }

    #[Test]
    public function answersMailchimpsCheckOfTheAddress(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->handle(
            (new ServerRequest('GET', self::URL))->withQueryParams([
                'binding' => 'newsletter',
                'key'     => Settings::SECRET,
            ]),
            Contexts::make(Settings::connected()),
        );

        static::assertSame([200, '{"received":0}'], self::answer($response));
    }

    #[Test]
    public function answersNotFoundForABindingItDoesNotHave(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->handle(
            self::call(['binding' => 'members', 'key' => Settings::SECRET]),
            Contexts::make(Settings::connected()),
        );

        static::assertSame([404, 'No such binding.'], self::answer($response));
    }

    #[Test]
    public function answersUnavailableUntilConnected(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), (new SyncKit())->factory))->handle(
            self::call(),
            Contexts::make(new ArraySettings()),
        );

        static::assertSame([503, 'The add-on isn\'t connected to Mailchimp.'], self::answer($response));
    }

    #[Test]
    public function answersUnavailableUntilTheSiteIsSetUp(): void
    {
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), FakeSyncFactory::notReady()))->handle(
            self::call(),
            Contexts::make(Settings::connected()),
        );

        static::assertSame([503, 'The site isn\'t set up to sync yet.'], self::answer($response));
    }

    #[Test]
    public function checksTheSignatureWithTheSecretMailchimpGave(): void
    {
        $kit  = new SyncKit();
        $time = $kit->clock->now()->getTimestamp();
        $body = http_build_query(self::UNSUBSCRIBE);
        $kit->webhooks->save(new RegisteredWebhook('newsletter', 'w1', Settings::SIGNING, $kit->clock->now()));
        $webhook = new EventsWebhook(Http::connections(new FakeHttpClient()), $kit->factory);

        $unsigned = $webhook->handle(self::call(body: self::UNSUBSCRIBE), Contexts::make(Settings::connected()));
        $signed   = $webhook->handle(
            self::call(body: self::UNSUBSCRIBE)
                ->withHeader(
                    'X-Mailchimp-Signature',
                    "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$body}", key: Settings::SIGNING),
                ),
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [
                [400, 'The webhook call was refused: it isn\'t signed'],
                [200, '{"received":1}'],
            ],
            [self::answer($unsigned), self::answer($signed)],
        );
    }

    #[Test]
    public function refusesACallForAnotherList(): void
    {
        $kit      = new SyncKit();
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), $kit->factory))->handle(
            self::call(body: [
                'type' => 'unsubscribe',
                'data' => ['email' => 'ada@example.com', 'list_id' => 'another'],
            ]),
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [400, 'The webhook call was refused: it isn\'t for the binding\'s audience'],
            self::answer($response),
        );
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('unverifiedProvider')]
    #[Test]
    public function refusesACallWithoutTheSecret(array $query, ArraySettings $settings): void
    {
        static::assertFalse((new EventsWebhook())->verify(self::call($query), Contexts::make($settings)));
    }

    #[Test]
    public function suppressesWhoeverLeftTheList(): void
    {
        $kit      = new SyncKit();
        $response = (new EventsWebhook(Http::connections(new FakeHttpClient()), $kit->factory))->handle(
            self::call(body: self::UNSUBSCRIBE),
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [[200, '{"received":1}'], SuppressionReason::Unsubscribed],
            [
                self::answer($response),
                $kit->stores->suppression->reason(
                    'newsletter',
                    $kit->stores->hasher->email(Email::fromString('ada@example.com')),
                ),
            ],
        );
    }
}
