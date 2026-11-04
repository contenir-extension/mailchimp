<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Webhook;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\WebhookInterface;
use Contenir\Extension\Api\Handler\WebhookResponse;
use Contenir\Extension\Mailchimp\Connections;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Contenir\Extension\Mailchimp\Sync\WebhookAddress;
use JsonException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function hash_equals;
use function is_string;

/**
 * events, at /api/extension/mailchimp/events: what Mailchimp calls when someone in a bound
 * audience unsubscribes, is cleaned or changes address, and the GET it checks the address with.
 * The call must carry the add-on's secret, and its audience must be the binding's; when Mailchimp
 * gave a signing secret as the webhook was registered, the call's signature must match it too.
 * Whoever left is suppressed, so the site never subscribes them again without fresh consent.
 *
 * @api
 */
final readonly class EventsWebhook implements WebhookInterface
{
    public function __construct(
        private Connections $connections = new Connections(),
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * @mago-expect analysis:mixed-assignment Query values are untyped; each is checked as text.
     */
    private static function query(ServerRequestInterface $request, string $name): string
    {
        $value = $request->getQueryParams()[$name] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @throws JsonException    Never: the answer is plain data.
     * @throws StorageException When the webhook, suppression or sync state can't be read or written.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): WebhookResponse
    {
        $connection = MailchimpSettings::from($context->settings())->connection();
        try {
            $sync    = $this->syncs->of($context);
            $binding = $sync->bindings->get(self::query($request, WebhookAddress::BINDING));
        } catch (NotReady) {
            return WebhookResponse::text('The site isn\'t set up to sync yet.', 503);
        } catch (UnknownBinding) {
            return WebhookResponse::text('No such binding.', 404);
        }

        if (null === $connection) {
            return WebhookResponse::text('The add-on isn\'t connected to Mailchimp.', 503);
        }

        try {
            $events = $this->connections
                ->webhooks($connection, $sync->clock)
                ->parse($request, SignedBinding::of($binding, $sync->webhooks->find($binding->name)));
        } catch (InvalidWebhook $e) {
            return WebhookResponse::text($e->getMessage(), 400);
        }

        $sync->remoteEvents()->apply($binding, $events);

        return WebhookResponse::json(['received' => count($events)]);
    }

    #[Override]
    public function verify(ServerRequestInterface $request, ExtensionContextInterface $context): bool
    {
        $secret = MailchimpSettings::from($context->settings())->webhookSecret;
        $key    = self::query($request, WebhookAddress::KEY);

        return null !== $secret && '' !== $key && hash_equals($secret, $key);
    }
}
