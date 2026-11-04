<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Webhook;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Audience\Service\WebhookReceiverInterface;
use Contenir\Audience\Service\WebhookRegistration;
use Contenir\Extension\Mailchimp\Service\Api;
use Contenir\Extension\Mailchimp\Service\Json;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function parse_str;
use function rawurlencode;

/**
 * Mailchimp's webhook for an audience. The add-on keeps a secret in the webhook's address, and
 * when the binding holds the signing secret Mailchimp gave at registration, each call must carry
 * a good signature too. Mailchimp checks the address answers a GET before it sends anything.
 *
 * @api
 */
final readonly class MailchimpWebhooks implements WebhookReceiverInterface
{
    /** The events registered: unsubscribes, cleaned addresses, and changed addresses */
    public const array EVENTS = [
        'subscribe'   => false,
        'unsubscribe' => true,
        'profile'     => false,
        'cleaned'     => true,
        'upemail'     => true,
        'campaign'    => false,
    ];

    /** The binding option holding the secret Mailchimp signs the webhook's calls with */
    public const string SECRET_OPTION = 'signing_secret';

    /**
     * Who the events come from: the person or the account's admins, but not this add-on's own
     * API requests, which the outbox already knows about.
     */
    public const array SOURCES = ['user' => true, 'admin' => true, 'api' => false];

    public function __construct(
        private Api $api,
        private ClockInterface $clock,
    ) {}

    /**
     * A form-encoded body, as Mailchimp sends its events.
     *
     * @return array<array-key, mixed>
     */
    private static function form(string $body): array
    {
        $fields = [];
        parse_str($body, $fields);

        return $fields;
    }

    #[Override]
    public function parse(ServerRequestInterface $request, Binding $binding): array
    {
        $method = $request->getMethod();
        if ('GET' === $method) {
            return [];
        }

        if ('POST' !== $method) {
            throw InvalidWebhook::because('Mailchimp sends its events by POST');
        }

        $body   = (string) $request->getBody();
        $secret = $binding->option(self::SECRET_OPTION);
        if (is_string($secret) && '' !== $secret) {
            Signature::verify(
                $request->getHeaderLine(Signature::HEADER),
                $body,
                $secret,
                $this->clock->now()->getTimestamp(),
            );
        }

        $payload = Json::document($body) ?? self::form($body);
        if (Json::string(Json::array($payload['data'] ?? null), 'list_id') !== $binding->list) {
            throw InvalidWebhook::because('it isn\'t for the binding\'s audience');
        }

        $event = Event::read($payload);

        return null === $event ? [] : [$event];
    }

    #[Override]
    public function register(Binding $binding, string $url): WebhookRegistration
    {
        $reply = $this->api->send('POST', 'lists/' . rawurlencode($binding->list) . '/webhooks', [
            'url'     => $url,
            'events'  => self::EVENTS,
            'sources' => self::SOURCES,
        ]);
        if (! $reply->ok()) {
            throw ConnectionRefused::because($reply->message());
        }

        $id = Json::string($reply->body, 'id');
        if ('' === $id) {
            throw ConnectionRefused::because('Mailchimp didn\'t give the webhook an id');
        }

        $secret = Json::string($reply->body, 'signing_secret');

        return new WebhookRegistration($id, '' === $secret ? null : $secret);
    }
}
