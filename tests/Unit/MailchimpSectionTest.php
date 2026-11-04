<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group as FormGroup;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Mailchimp\MailchimpSection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;

#[Group('unit')]
final class MailchimpSectionTest extends TestCase
{
    #[Test]
    public function asksForTheKeyAndWebhookSecret(): void
    {
        static::assertSame(
            [
                ['api_key',        'password', true],
                ['webhook_secret', 'password', true],
            ],
            array_map(
                static fn(Field $field): array => [$field->name, $field->type, $field->local],
                MailchimpSection::define(new Handle('mailchimp'))->form->fields,
            ),
        );
    }

    #[Test]
    public function confirmsBeforeRegisteringWebhooksAgain(): void
    {
        static::assertSame(
            ['test-connection' => false, 'sync-now' => false, 'register-webhooks' => true],
            array_column(
                array_map(
                    static fn(Operation $operation): array => [$operation->name, $operation->confirm],
                    MailchimpSection::define(new Handle('mailchimp'))->operations,
                ),
                1,
                0,
            ),
        );
    }

    #[Test]
    public function groupsTheConnectionApartFromTheWebhook(): void
    {
        static::assertSame(
            [['connection', 'Connection', [['api_key']]], ['webhook', 'Webhook', [['webhook_secret']]]],
            array_map(
                static fn(FormGroup $group): array => [$group->id, $group->legend, $group->rows],
                MailchimpSection::define(new Handle('mailchimp'))->form->groups,
            ),
        );
    }

    #[Test]
    public function linksToTheSyncAndBindingsScreens(): void
    {
        static::assertSame(
            ['/admin/extension/mailchimp/sync', '/admin/extension/mailchimp/bindings'],
            array_map(
                static fn(Contribution $contribution): string => $contribution->url,
                MailchimpSection::define(new Handle('mailchimp'))->contributions,
            ),
        );
    }

    #[Test]
    public function saysWhereEachValueComesFrom(): void
    {
        static::assertSame(
            [
                'From Mailchimp under Profile > Extras > API keys. It ends with the account\'s data centre, such as'
                    . ' -us21. Kept in this environment\'s local settings.',
                'Made by Re-register webhooks, and part of the address Mailchimp calls. Clear it and'
                    . ' re-register to change it.',
            ],
            array_map(
                static fn(Field $field): string => $field->description,
                MailchimpSection::define(new Handle('mailchimp'))->form->fields,
            ),
        );
    }

    #[Test]
    public function sitsUnderIntegrationAsMailchimp(): void
    {
        $section = MailchimpSection::define(new Handle('mailchimp'));

        static::assertSame(
            ['mailchimp', 'Mailchimp', SettingsCategory::Integration],
            [$section->name, $section->title, $section->category],
        );
    }

    #[Test]
    public function warnsThatReRegisteringMovesTheWebhook(): void
    {
        static::assertSame(
            'Register each audience\'s webhook again? Mailchimp then calls the new address, and the old one stops.',
            MailchimpSection::define(new Handle('mailchimp'))->operations[2]->confirmMessage,
        );
    }
}
