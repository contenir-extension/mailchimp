<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Mailchimp\Service\Api;
use Contenir\Extension\Mailchimp\Service\Connection;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\NetworkError;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[Group('unit')]
final class ApiTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, int|null}>
     */
    public static function waitProvider(): array
    {
        return [
            'Retry-After'            => [['Retry-After' => '30'], 30],
            'a date it can\'t count' => [['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], null],
            'no wait asked'          => [[], null],
        ];
    }

    #[Test]
    public function answersARequestThatNeverArrivedWithWhy(): void
    {
        $reply = Apis::over(new FakeHttpClient(new NetworkError('Connection timed out')))->send('GET', 'lists');

        static::assertSame([0, 'Mailchimp couldn\'t be reached: Connection timed out'], [
            $reply->status,
            $reply->message(),
        ]);
    }

    #[Test]
    public function connectsThroughGuzzleUnlessGivenAnotherClient(): void
    {
        $connection = new Connection(Apis::KEY);

        static::assertSame($connection, (new Api($connection))->connection);
    }

    #[Test]
    #[DataProvider('waitProvider')]
    public function readsHowLongMailchimpAskedToWait(array $headers, ?int $wait): void
    {
        $reply = Apis::over(new FakeHttpClient(Replies::error(429, 'Too Many Requests', 'Slow down', $headers)))->send(
            'GET',
            'lists',
        );

        static::assertSame($wait, $reply->retryAfter);
    }

    #[Test]
    public function readsNoBodyFromAnEmptyReply(): void
    {
        static::assertNull(Apis::over(new FakeHttpClient(new Response(204)))->send(
            'DELETE',
            'lists/a1/members/m1',
        )->body);
    }

    #[Test]
    public function readsTheReply(): void
    {
        $reply = Apis::over(new FakeHttpClient(Replies::json(200, ['id' => 'm1'])))->send('GET', 'lists/a1/members/m1');

        static::assertSame([200, ['id' => 'm1']], [$reply->status, $reply->body]);
    }

    #[Test]
    public function sendsTheBodyAsJson(): void
    {
        $http = new FakeHttpClient(Replies::json(200, []));
        Apis::over($http)->send('POST', 'lists/a1/webhooks', ['url' => 'https://example.com/a/b', 'name' => 'Zoë']);

        static::assertSame(
            ['application/json', '{"url":"https://example.com/a/b","name":"Zoë"}'],
            [$http->requests[0]->getHeaderLine('Content-Type'), (string) $http->requests[0]->getBody()],
        );
    }

    #[Test]
    public function sendsToTheDataCentreWithTheQuery(): void
    {
        $http = new FakeHttpClient(Replies::json(200, []));
        Apis::over($http)->send('GET', 'lists', query: ['fields' => 'lists.id,lists.name']);

        static::assertSame(
            ['GET', Apis::BASE . 'lists?fields=lists.id%2Clists.name'],
            [$http->requests[0]->getMethod(), (string) $http->requests[0]->getUri()],
        );
    }

    #[Test]
    public function signsInAndAsksForJson(): void
    {
        $http = new FakeHttpClient(Replies::json(200, []));
        Apis::over($http)->send('GET', '');

        static::assertSame(
            ['Basic ' . base64_encode('contenir:' . Apis::KEY), 'application/json', '', Apis::BASE],
            [
                $http->requests[0]->getHeaderLine('Authorization'),
                $http->requests[0]->getHeaderLine('Accept'),
                $http->requests[0]->getHeaderLine('Content-Type'),
                (string) $http->requests[0]->getUri(),
            ],
        );
    }

    #[Test]
    public function stopsWhenTheKeyIsRefused(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Your API key may be invalid.');

        Apis::over(new FakeHttpClient(Replies::error(401, 'API Key Invalid', 'Your API key may be invalid.')))->send(
            'GET',
            '',
        );
    }

    #[Test]
    public function stopsWhenTheKeyLacksAccess(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: The key is disabled.');

        Apis::over(new FakeHttpClient(Replies::error(403, 'Forbidden', 'The key is disabled.')))->send('GET', '');
    }
}
