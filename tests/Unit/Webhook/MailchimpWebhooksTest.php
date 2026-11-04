<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Webhook;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Audience\Service\WebhookRegistration;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Webhook\MailchimpWebhooks;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function hash_hmac;
use function http_build_query;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[Group('unit')]
final class MailchimpWebhooksTest extends TestCase
{
    private const string SIGNED_WITH = 'signed-in-tests';

    private const string URL = 'https://www.example.com/api/extension/mailchimp/events/s3cr3t';

    /** @var array<string, mixed> */
    private const array UNSUBSCRIBE = [
        'type'     => 'unsubscribe',
        'fired_at' => '2026-11-04 09:30:00',
        'data'     => [
            'action'  => 'unsub',
            'reason'  => 'manual',
            'email'   => 'ada@example.com',
            'list_id' => People::LIST,
        ],
    ];

    private static function call(string $body): ServerRequest
    {
        return new ServerRequest('POST', self::URL, ['Content-Type' => 'application/x-www-form-urlencoded'], $body);
    }

    /**
     * @param list<\Contenir\Audience\Service\RemoteEvent> $events
     *
     * @return list<array{RemoteEventKind, string}>
     */
    private static function summary(array $events): array
    {
        return array_map(static fn($event): array => [$event->kind, $event->email->value], $events);
    }

    private static function webhooks(
        FakeHttpClient $http = new FakeHttpClient(),
        MutableClock $clock = new MutableClock(),
    ): MailchimpWebhooks {
        return new MailchimpWebhooks(Apis::over($http), $clock);
    }

    #[Test]
    public function answersMailchimpsCheckWithNoEvents(): void
    {
        static::assertSame([], self::webhooks()->parse(new ServerRequest('GET', self::URL), People::binding()));
    }

    #[Test]
    public function readsAFormEncodedEvent(): void
    {
        $events = self::webhooks()->parse(self::call(http_build_query(self::UNSUBSCRIBE)), People::binding());

        static::assertSame([[RemoteEventKind::Unsubscribed, 'ada@example.com']], self::summary($events));
    }

    #[Test]
    public function readsAJsonEvent(): void
    {
        $events = self::webhooks()
            ->parse(self::call(json_encode(self::UNSUBSCRIBE, JSON_THROW_ON_ERROR)), People::binding());

        static::assertSame([[RemoteEventKind::Unsubscribed, 'ada@example.com']], self::summary($events));
    }

    #[Test]
    public function readsAnUnsignedCallWhenTheSecretIsEmpty(): void
    {
        $events = self::webhooks()
            ->parse(
                self::call(http_build_query(self::UNSUBSCRIBE)),
                People::binding(options: ['signing_secret' => '']),
            );

        static::assertCount(1, $events);
    }

    #[Test]
    public function readsASignedCallWhenTheBindingHoldsTheSecret(): void
    {
        $clock = new MutableClock();
        $body  = http_build_query(self::UNSUBSCRIBE);
        $time  = $clock->now()->getTimestamp();
        $call  = self::call($body)
            ->withHeader(
                'X-Mailchimp-Signature',
                "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$body}", self::SIGNED_WITH),
            );

        $events = self::webhooks(clock: $clock)
            ->parse($call, People::binding(options: ['signing_secret' => self::SIGNED_WITH]));

        static::assertSame([[RemoteEventKind::Unsubscribed, 'ada@example.com']], self::summary($events));
    }

    #[Test]
    public function readsNoEventsFromOneItPassesOver(): void
    {
        $body = http_build_query(['type' => 'subscribe'] + self::UNSUBSCRIBE);

        static::assertSame([], self::webhooks()->parse(self::call($body), People::binding()));
    }

    #[Test]
    public function refusesACallForAnotherAudience(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: it isn\'t for the binding\'s audience');

        self::webhooks()->parse(self::call(http_build_query(['data' => ['list_id' => 'another']]
            + self::UNSUBSCRIBE)), People::binding());
    }

    #[Test]
    public function refusesACallThatIsNeitherACheckNorAPost(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: Mailchimp sends its events by POST');

        self::webhooks()->parse(new ServerRequest('PUT', self::URL), People::binding());
    }

    #[Test]
    public function refusesACallWithoutData(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: it isn\'t for the binding\'s audience');

        self::webhooks()->parse(self::call('type=unsubscribe'), People::binding());
    }

    #[Test]
    public function refusesAnUnsignedCallWhenTheBindingHoldsTheSecret(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: it isn\'t signed');

        self::webhooks()
            ->parse(
                self::call(http_build_query(self::UNSUBSCRIBE)),
                People::binding(options: ['signing_secret' => self::SIGNED_WITH]),
            );
    }

    #[Test]
    public function refusesARegistrationMailchimpTurnedDown(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: The URL must be reachable');

        self::webhooks(new FakeHttpClient(Replies::error(400, 'Invalid Resource', 'The URL must be reachable')))
            ->register(People::binding(), self::URL);
    }

    #[Test]
    public function refusesARegistrationWithoutAnId(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Mailchimp didn\'t give the webhook an id');

        self::webhooks(new FakeHttpClient(Replies::json(200, ['url' => self::URL])))
            ->register(People::binding(), self::URL);
    }

    #[Test]
    public function registersAnUnsignedWebhookWhenMailchimpGivesNoSecret(): void
    {
        $registration = self::webhooks(new FakeHttpClient(Replies::json(200, ['id' => 'wh1'])))
            ->register(People::binding(), self::URL);

        static::assertEquals(new WebhookRegistration('wh1'), $registration);
    }

    #[Test]
    public function registersForUnsubscribesCleanedAndChangedAddressesFromPeopleAndAdmins(): void
    {
        $http         = new FakeHttpClient(Replies::json(200, ['id' => 'wh1', 'signing_secret' => self::SIGNED_WITH]));
        $registration = self::webhooks($http)->register(People::binding(), self::URL);

        static::assertEquals(
            [
                Apis::BASE . 'lists/' . People::LIST . '/webhooks',
                [
                    'url'     => self::URL,
                    'events'  => [
                        'subscribe'   => false,
                        'unsubscribe' => true,
                        'profile'     => false,
                        'cleaned'     => true,
                        'upemail'     => true,
                        'campaign'    => false,
                    ],
                    'sources' => ['user' => true, 'admin' => true, 'api' => false],
                ],
                new WebhookRegistration('wh1', self::SIGNED_WITH),
            ],
            [
                (string) $http->requests[0]->getUri(),
                json_decode((string) $http->requests[0]->getBody(), associative: true, flags: JSON_THROW_ON_ERROR),
                $registration,
            ],
        );
    }

    #[Test]
    public function signedCallsAreJudgedByTheClocksTime(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: its signature is more than five minutes out');

        $clock = new MutableClock();
        $body  = http_build_query(self::UNSUBSCRIBE);
        $time  = $clock->now()->getTimestamp();
        $call  = self::call($body)
            ->withHeader(
                'X-Mailchimp-Signature',
                "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$body}", self::SIGNED_WITH),
            );
        $clock->advance('+6 minutes');

        self::webhooks(clock: $clock)->parse($call, People::binding(options: ['signing_secret' => self::SIGNED_WITH]));
    }
}
