<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Factory;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Answers from Mailchimp's API, as its documentation shows them.
 */
final class Replies
{
    /**
     * The account a key reaches, as GET / gives it.
     */
    public static function account(string $name = 'Sector7g'): ResponseInterface
    {
        return self::json(200, ['account_id' => 'a1', 'account_name' => $name]);
    }

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
     * The account's audiences, as GET lists gives them: name by id.
     *
     * @param array<string, string> $lists
     */
    public static function lists(array $lists): ResponseInterface
    {
        $rows = [];
        foreach ($lists as $id => $name) {
            $rows[] = ['id' => $id, 'name' => $name];
        }

        return self::json(200, ['lists' => $rows, 'total_items' => count($rows)]);
    }

    /**
     * A member as Mailchimp answers an add, update or unsubscribe.
     */
    public static function member(string $id, string $status = 'subscribed'): ResponseInterface
    {
        return self::json(200, ['id' => $id, 'email_address' => 'ada@example.com', 'status' => $status]);
    }

    /**
     * The 401 Mailchimp gives for a key it doesn't know.
     */
    public static function refused(): ResponseInterface
    {
        return self::error(
            401,
            'API Key Invalid',
            'Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.',
        );
    }

    /**
     * A webhook Mailchimp just registered, with the secret it signs its calls with.
     */
    public static function webhook(
        string $id,
        #[SensitiveParameter]
        ?string $secret = null,
    ): ResponseInterface {
        return self::json(200, null === $secret ? ['id' => $id] : ['id' => $id, 'signing_secret' => $secret]);
    }

    /**
     * An audience's webhooks, as GET lists/{id}/webhooks gives them.
     *
     * @param list<string> $ids
     */
    public static function webhooks(array $ids): ResponseInterface
    {
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = ['id' => $id, 'url' => 'https://x', 'list_id' => People::LIST];
        }

        return self::json(200, ['webhooks' => $rows, 'total_items' => count($rows)]);
    }
}
