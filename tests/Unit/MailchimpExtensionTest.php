<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit;

use Contenir\Extension\Api\Event\Record\RecordPostDelete;
use Contenir\Extension\Api\Event\Record\RecordPostSave;
use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Listener\Delivery;
use Contenir\Extension\Api\Listener\ListenerDeclaration;
use Contenir\Extension\Api\Migration\Target;
use Contenir\Extension\Api\Registration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Mailchimp\Check\ConnectionCheck;
use Contenir\Extension\Mailchimp\Check\OutboxCheck;
use Contenir\Extension\Mailchimp\Check\SetupCheck;
use Contenir\Extension\Mailchimp\Check\WebhookCheck;
use Contenir\Extension\Mailchimp\Handler\BindingsScreen;
use Contenir\Extension\Mailchimp\Handler\SyncScreen;
use Contenir\Extension\Mailchimp\Listener\RemoveRecord;
use Contenir\Extension\Mailchimp\Listener\SyncRecord;
use Contenir\Extension\Mailchimp\MailchimpExtension;
use Contenir\Extension\Mailchimp\Schedule\Drain;
use Contenir\Extension\Mailchimp\Webhook\EventsWebhook;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function dirname;

#[Group('unit')]
final class MailchimpExtensionTest extends TestCase
{
    private static function registration(): Registration
    {
        $registry = new Registry('mailchimp');
        (new MailchimpExtension())->register($registry);

        return $registry->registration();
    }

    #[Test]
    public function checksSetupConnectionOutboxAndWebhooks(): void
    {
        static::assertSame(
            [
                'setup'      => SetupCheck::class,
                'connection' => ConnectionCheck::class,
                'outbox'     => OutboxCheck::class,
                'webhooks'   => WebhookCheck::class,
            ],
            array_map(static fn($declaration): string => $declaration->class, self::registration()->diagnostics),
        );
    }

    #[Test]
    public function createsItsTablesInTheSitesDatabase(): void
    {
        $set = self::registration()->migrations[0];

        static::assertSame(
            [dirname(__DIR__, levels: 2) . '/src/Schema', MailchimpExtension::SCHEMA, Target::Site],
            [$set->path, $set->namespace, $set->target],
        );
    }

    #[Test]
    public function drainsTheOutboxEveryMinute(): void
    {
        $schedule = self::registration()->schedules['drain'];

        static::assertSame([Drain::class, 1, null], [
            $schedule->class,
            $schedule->frequency->minutes,
            $schedule->frequency->cron,
        ]);
    }

    #[Test]
    public function hearsSavesAndDeletesAtOnceSoTheOutboxIsWrittenWithTheRecord(): void
    {
        static::assertSame(
            [
                ['sync-record',   RecordPostSave::class,   SyncRecord::class,   Delivery::Immediate],
                ['remove-record', RecordPostDelete::class, RemoveRecord::class, Delivery::Immediate],
            ],
            array_map(
                static fn(ListenerDeclaration $listener): array => [
                    $listener->name,
                    $listener->event,
                    $listener->listener,
                    $listener->delivery,
                ],
                self::registration()->listeners,
            ),
        );
    }

    #[Test]
    public function offersTheSyncAndBindingsScreens(): void
    {
        $handlers = self::registration()->handlers;

        static::assertSame(
            [
                ['sync',                'bindings'],
                [SyncScreen::class,     null],
                [BindingsScreen::class, Privilege::View],
            ],
            [
                array_keys($handlers),
                [$handlers['sync']->class, $handlers['sync']->privilege],
                [$handlers['bindings']->class, $handlers['bindings']->privilege],
            ],
        );
    }

    #[Test]
    public function receivesEventsAtItsPublicWebhook(): void
    {
        $webhook = self::registration()->webhooks['events'];

        static::assertSame(
            [EventsWebhook::class, '/api/extension/mailchimp/events'],
            [$webhook->class, $webhook->path],
        );
    }

    #[Test]
    public function registersItsSettingsWithNoFieldTypes(): void
    {
        $registration = self::registration();

        static::assertSame(
            [
                ['mailchimp'],
                [],
            ],
            [array_keys($registration->settingsSections), array_keys($registration->fieldTypes)],
        );
    }
}
