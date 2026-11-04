<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Handler;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Field\FieldRule;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Api\Handler\Table;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function implode;
use function sprintf;

/**
 * Bindings, read-only in v1 (W2-1): each binding the add-on's config file sets, with the list it
 * feeds, where its people come from, exactly which fields it sends, the consent it records, what
 * a delete does, and its webhook.
 *
 * @api
 */
final readonly class BindingsScreen implements HandlerInterface
{
    public const string NAME = 'bindings';

    public function __construct(
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    private static function field(FieldRule $rule): string
    {
        return sprintf('%s ← %s', $rule->remote, $rule->local ?? sprintf('"%s"', (string) $rule->constant));
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function row(Binding $binding, ?RegisteredWebhook $webhook): array
    {
        return [
            'name'    => $binding->name,
            'list'    => $binding->list,
            'source'  => $binding->isFromSite() ? 'The site\'s sign-up forms' : $binding->source,
            'fields'  => implode(', ', [
                sprintf('email ← %s', $binding->emailField),
                ...array_map(self::field(...), $binding->fields->rules),
            ]),
            'consent' => $binding->consent,
            'delete'  => $binding->onDelete->value,
            'webhook' => null === $webhook
                ? 'Not registered'
                : sprintf('Registered %s', $webhook->registeredAt->format('j M Y H:i')),
        ];
    }

    /**
     * @throws StorageException When the registered webhooks can't be read.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady $e) {
            return HandlerResult::screen(new Screen(
                title: 'Bindings',
                intro: $e->getMessage(),
            ));
        }

        $webhooks = $sync->webhooks->all();

        return HandlerResult::screen(new Screen(
            title: 'Bindings',
            intro: 'Which audience each sign-up form or section feeds, and exactly what it sends. They are set in'
                . ' the site\'s config/autoload/extensions/mailchimp.global.php.',
            table: new Table(
                [
                    'name'    => 'Binding',
                    'list'    => 'Audience',
                    'source'  => 'From',
                    'fields'  => 'Sends',
                    'consent' => 'Consent wording',
                    'delete'  => 'On delete',
                    'webhook' => 'Webhook',
                ],
                array_map(
                    /** @return array<string, scalar|null> */
                    static fn(Binding $binding): array => self::row($binding, $webhooks[$binding->name] ?? null),
                    $sync->bindings->all(),
                ),
            ),
        ));
    }
}
