<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Check;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Mailchimp\Connections;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Contenir\Extension\Mailchimp\Service\Api;
use Contenir\Extension\Mailchimp\Service\Json;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;

use function implode;
use function rawurlencode;
use function sprintf;

/**
 * Whether each binding's webhook is registered and Mailchimp still has it: a webhook it deleted
 * means unsubscribes and cleaned addresses no longer reach the site.
 *
 * @api
 */
final readonly class WebhookCheck implements CheckInterface
{
    public const string NAME = 'webhooks';

    public function __construct(
        private Connections $connections = new Connections(),
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * What's wrong with one binding's webhook, or null when Mailchimp has it.
     *
     * @throws ConnectionRefused
     */
    private static function problem(Api $api, Binding $binding, ?RegisteredWebhook $webhook): ?string
    {
        if (null === $webhook) {
            return sprintf('the webhook for %s isn\'t registered', $binding->name);
        }

        $reply = $api->send('GET', sprintf('lists/%s/webhooks', rawurlencode($binding->list)));
        if (! $reply->ok()) {
            return sprintf('the webhooks for %s couldn\'t be read: %s', $binding->name, $reply->message());
        }

        foreach (Json::rows(Json::array($reply->body)['webhooks'] ?? null) as $row) {
            if (Json::string($row, 'id') === $webhook->remoteId) {
                return null;
            }
        }

        return sprintf('the webhook for %s is no longer at Mailchimp', $binding->name);
    }

    /**
     * @throws StorageException When the registered webhooks can't be read.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $connection = MailchimpSettings::from($context->settings())->connection();
        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady) {
            return CheckResult::skipped('The site isn\'t set up to sync yet.');
        }

        if (null === $connection) {
            return CheckResult::skipped('The add-on isn\'t connected to Mailchimp yet.');
        }

        $api      = $this->connections->api($connection);
        $webhooks = $sync->webhooks->all();
        $problems = [];
        try {
            foreach ($sync->bindings->all() as $binding) {
                $problem = self::problem($api, $binding, $webhooks[$binding->name] ?? null);
                if (null !== $problem) {
                    $problems[] = $problem;
                }
            }
        } catch (ConnectionRefused) {
            return CheckResult::skipped('Mailchimp refused the connection: see the connection check.');
        }

        return [] === $problems
            ? CheckResult::success('Mailchimp calls the webhook for every binding.')
            : CheckResult::warning(sprintf(
                'Unsubscribes and cleaned addresses don\'t reach the site: %s. Press Re-register webhooks in the'
                    . ' Mailchimp settings.',
                implode('; ', $problems),
            ));
    }

    #[Override]
    public function label(): string
    {
        return 'Mailchimp webhooks';
    }
}
