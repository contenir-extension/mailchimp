<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Extension\Mailchimp\Service\Connection;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Http;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hash_hmac;
use function http_build_query;

#[Group('unit')]
final class ConnectionsTest extends TestCase
{
    #[Test]
    public function checksSignaturesAgainstTheClockItIsGiven(): void
    {
        $clock = new MutableClock();
        $body  = http_build_query([
            'type' => 'unsubscribe',
            'data' => [
                'email'   => 'ada@example.com',
                'list_id' => People::LIST,
            ],
        ]);
        $time = $clock->now()->getTimestamp();
        $clock->advance('+1 hour');

        $this->expectExceptionMessage('its signature is more than five minutes out');

        Http::connections(new FakeHttpClient())
            ->webhooks(new Connection(Apis::KEY), $clock)
            ->parse(
                (new ServerRequest('POST', 'https://www.example.com/hook', [], $body))->withHeader(
                    'X-Mailchimp-Signature',
                    "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$body}", key: Settings::SIGNING),
                ),
                new Binding('newsletter', People::LIST, options: ['signing_secret' => Settings::SIGNING]),
            );
    }

    #[Test]
    public function givesAnAdapterOverItsHttpClient(): void
    {
        $http = new FakeHttpClient(Replies::account());

        Http::connections($http)->adapter(new Connection(Apis::KEY))->verifyConnection();

        static::assertSame(Apis::BASE . '?fields=account_id%2Caccount_name', (string) $http->requests[0]->getUri());
    }

    #[Test]
    public function givesAnApiForTheConnection(): void
    {
        static::assertSame(
            Apis::BASE,
            Http::connections(new FakeHttpClient())->api(new Connection(Apis::KEY))->connection->base,
        );
    }

    #[Test]
    public function givesWebhooksOverItsHttpClient(): void
    {
        $registration = Http::connections(new FakeHttpClient(Replies::webhook('w1', 'signed')))
            ->webhooks(new Connection(Apis::KEY), new MutableClock())
            ->register(new Binding('newsletter', People::LIST), 'https://www.example.com/hook');

        static::assertSame(['w1', 'signed'], [$registration->id, $registration->signingSecret]);
    }
}
