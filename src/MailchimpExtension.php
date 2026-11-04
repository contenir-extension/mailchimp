<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp;

use Contenir\Extension\Api\Event\Record\RecordPostDelete;
use Contenir\Extension\Api\Event\Record\RecordPostSave;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Listener\Delivery;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Api\Schedule\Frequency;
use Contenir\Extension\Mailchimp\Check\ConnectionCheck;
use Contenir\Extension\Mailchimp\Check\OutboxCheck;
use Contenir\Extension\Mailchimp\Check\SetupCheck;
use Contenir\Extension\Mailchimp\Check\WebhookCheck;
use Contenir\Extension\Mailchimp\Handler\BindingsScreen;
use Contenir\Extension\Mailchimp\Handler\SyncScreen;
use Contenir\Extension\Mailchimp\Listener\RemoveRecord;
use Contenir\Extension\Mailchimp\Listener\SyncRecord;
use Contenir\Extension\Mailchimp\Schedule\Drain;
use Contenir\Extension\Mailchimp\Sync\WebhookAddress;
use Contenir\Extension\Mailchimp\Webhook\EventsWebhook;
use Override;

/**
 * The Mailchimp add-on: keeps an account's audiences in step with the site's sign-ups and the
 * records a site binds, through an outbox drained every minute, and hears back about
 * unsubscribes, cleaned addresses and changed addresses through its signed webhook.
 *
 * @api
 */
final class MailchimpExtension implements ExtensionInterface
{
    /**
     * The add-on's table prefix.
     */
    public const string PREFIX = 'ext_mailchimp_';

    /**
     * The namespace of the add-on's migrations, in src/Schema.
     */
    public const string SCHEMA = __NAMESPACE__ . '\\Schema';

    #[Override]
    public function register(Registry $registry): void
    {
        $registry->settingsSection(MailchimpSection::define($registry->handle()));
        $registry->listen(SyncRecord::NAME, RecordPostSave::class, SyncRecord::class, delivery: Delivery::Immediate);
        $registry->listen(
            RemoveRecord::NAME,
            RecordPostDelete::class,
            RemoveRecord::class,
            delivery: Delivery::Immediate,
        );
        $registry->schedule(Drain::NAME, Drain::class, Frequency::everyMinutes(1));
        $registry->webhook(WebhookAddress::NAME, EventsWebhook::class);
        $registry->migrations(__DIR__ . '/Schema', self::SCHEMA);
        $registry->handler(SyncScreen::NAME, SyncScreen::class);
        $registry->handler(BindingsScreen::NAME, BindingsScreen::class, Privilege::View);
        $registry->diagnostic(SetupCheck::NAME, SetupCheck::class);
        $registry->diagnostic(ConnectionCheck::NAME, ConnectionCheck::class);
        $registry->diagnostic(OutboxCheck::NAME, OutboxCheck::class);
        $registry->diagnostic(WebhookCheck::NAME, WebhookCheck::class);
    }
}
