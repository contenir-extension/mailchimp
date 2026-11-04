<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

use Contenir\Audience\Service\ConnectionRefused;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function ctype_digit;
use function http_build_query;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Sends one JSON request to Mailchimp's API and reads its answer. A refused key (401 or
 * 403) stops everything, so it throws; anything else comes back as a reply, including a request
 * that never got there.
 *
 * @api
 */
final readonly class Api
{
    /**
     * Guzzle's options: at most 5 seconds to connect and 10 for an answer.
     */
    public const array GUZZLE = ['timeout' => 10, 'connect_timeout' => 5];

    public function __construct(
        public Connection $connection,
        private ClientInterface $http = new Guzzle(self::GUZZLE),
        private RequestFactoryInterface $requests = new HttpFactory(),
        private StreamFactoryInterface $streams = new HttpFactory(),
    ) {}

    /**
     * The body's JSON, or null for an empty or other body.
     */
    private static function decode(ResponseInterface $response): mixed
    {
        try {
            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Seconds to wait before asking again, when Mailchimp says.
     */
    private static function wait(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @param array<array-key, mixed>|null $body
     * @param array<string, string>        $query
     *
     * @throws ConnectionRefused
     */
    public function send(string $method, string $path, ?array $body = null, array $query = []): Reply
    {
        $url     = $this->connection->base . $path . ([] === $query ? '' : '?' . http_build_query($query));
        $request = $this->requests
            ->createRequest($method, $url)
            ->withHeader('Authorization', $this->connection->authorization())
            ->withHeader('Accept', 'application/json');
        if (null !== $body) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody($this->streams->createStream(json_encode(
                    $body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                )));
        }

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            return new Reply(0, failure: "Mailchimp couldn't be reached: {$exception->getMessage()}");
        }

        $reply = new Reply($response->getStatusCode(), self::decode($response), self::wait($response));
        if (401 === $reply->status || 403 === $reply->status) {
            throw ConnectionRefused::because($reply->message());
        }

        return $reply;
    }
}
