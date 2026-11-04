<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Factory;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers from Mailchimp's API, as its documentation shows them.
 */
final class Replies
{
    public static function deleted(): ResponseInterface
    {
        return new Response(204);
    }

    /**
     * An error in Mailchimp's problem format.
     *
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $title, string $detail, array $headers = []): ResponseInterface
    {
        return self::json(
            $status,
            [
                'type'     => 'https://mailchimp.com/developer/marketing/docs/errors/',
                'title'    => $title,
                'status'   => $status,
                'detail'   => $detail,
                'instance' => '2b6a1c9e-1f4e-4c3e-9a1b-7d0f3e2c1a00',
            ],
            $headers,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', ...$headers],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * A member as Mailchimp answers an add, update or unsubscribe.
     */
    public static function member(string $id, string $status = 'subscribed'): ResponseInterface
    {
        return self::json(200, ['id' => $id, 'email_address' => 'ada@example.com', 'status' => $status]);
    }
}
