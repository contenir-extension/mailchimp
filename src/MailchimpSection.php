<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Extension\Mailchimp\Handler\BindingsScreen;
use Contenir\Extension\Mailchimp\Handler\SyncScreen;
use Contenir\Extension\Mailchimp\Operation\RegisterWebhooks;
use Contenir\Extension\Mailchimp\Operation\SyncNow;
use Contenir\Extension\Mailchimp\Operation\TestConnection;

use function sprintf;

/**
 * The add-on's settings section, Mailchimp under Integration: the API key, kept in this
 * environment's local settings, and the secret in the webhook's address, with Test connection,
 * Sync all now and Re-register webhooks.
 *
 * @api
 */
final class MailchimpSection
{
    public const string NAME = 'mailchimp';

    /**
     * @mago-expect lint:no-literal-password The field's name, not a secret.
     */
    public const string API_KEY = 'api_key';

    /**
     * @mago-expect lint:no-literal-password The field's name, not a secret.
     */
    public const string WEBHOOK_SECRET = 'webhook_secret';

    /**
     * The section, with its field type named for the add-on's handle.
     */
    public static function define(Handle $handle): SettingsSection
    {
        return new SettingsSection(
            name: self::NAME,
            title: 'Mailchimp',
            category: SettingsCategory::Integration,
            form: new Form(
                [
                    Field::password(
                        name: self::API_KEY,
                        label: 'API key',
                        description: 'From Mailchimp under Profile > Extras > API keys. It ends with the account\'s'
                            . ' data centre, such as -us21. Kept in this environment\'s local settings.',
                    ),
                    Field::password(
                        name: self::WEBHOOK_SECRET,
                        label: 'Webhook secret',
                        description: 'Made by Re-register webhooks, and part of the address Mailchimp calls.'
                            . ' Clear it and re-register to change it.',
                    ),
                ],
                [
                    new Group('connection', 'Connection', [self::API_KEY]),
                    new Group('webhook', 'Webhook', [self::WEBHOOK_SECRET]),
                ],
            ),
            operations: [
                new Operation(
                    name: TestConnection::NAME,
                    label: 'Test connection',
                    icon: 'plug-connected',
                    privilege: 'update',
                    handler: TestConnection::class,
                    surface: 'view',
                ),
                new Operation(
                    name: SyncNow::NAME,
                    label: 'Sync all now',
                    icon: 'refresh',
                    privilege: 'update',
                    handler: SyncNow::class,
                    surface: 'view',
                ),
                new Operation(
                    name: RegisterWebhooks::NAME,
                    label: 'Re-register webhooks',
                    icon: 'webhook',
                    privilege: 'update',
                    handler: RegisterWebhooks::class,
                    surface: 'view',
                    confirm: true,
                    confirmMessage: 'Register each audience\'s webhook again? Mailchimp then calls the new address,'
                        . ' and the old one stops.',
                ),
            ],
            contributions: [
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    label: 'Sync',
                    icon: 'refresh',
                    url: sprintf('/admin/extension/%s/%s', $handle->value, SyncScreen::NAME),
                ),
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    label: 'Bindings',
                    icon: 'list',
                    url: sprintf('/admin/extension/%s/%s', $handle->value, BindingsScreen::NAME),
                ),
            ],
        );
    }
}
