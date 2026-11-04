<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Check;

use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Mailchimp\Check\WebhookCheck;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Http;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class WebhookCheckTest extends TestCase
{
    /**
     * A kit with the newsletter and members bindings, newsletter's webhook registered as w1.
     */
    private static function kit(): SyncKit
    {
        $kit = new SyncKit([...SyncKit::NEWSLETTER, ...SyncKit::MEMBERS]);
        $kit->webhooks->save(new RegisteredWebhook('newsletter', 'w1', null, $kit->clock->now()));
        $kit->webhooks->save(new RegisteredWebhook('members', 'w2', null, $kit->clock->now()));

        return $kit;
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Mailchimp webhooks', (new WebhookCheck())->label());
    }

    #[Test]
    public function isSkippedUntilConnected(): void
    {
        $result = (new WebhookCheck(Http::connections(new FakeHttpClient()), self::kit()->factory))->check(
            Contexts::make(new ArraySettings()),
        );

        static::assertSame(Status::Skipped, $result->status);
    }

    #[Test]
    public function isSkippedUntilTheSiteIsSetUp(): void
    {
        $result = (new WebhookCheck(Http::connections(new FakeHttpClient()), FakeSyncFactory::notReady()))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame(Status::Skipped, $result->status);
    }

    #[Test]
    public function leavesARefusedConnectionToTheConnectionCheck(): void
    {
        $result = (new WebhookCheck(
            Http::connections(new FakeHttpClient(Replies::refused())),
            self::kit()->factory,
        ))->check(Contexts::make(Settings::connected()));

        static::assertSame(
            [Status::Skipped, 'Mailchimp refused the connection: see the connection check.'],
            [$result->status, $result->message],
        );
    }

    #[Test]
    public function passesWhenMailchimpCallsEveryWebhook(): void
    {
        $http = new FakeHttpClient(
            Replies::webhooks(['w0', 'w1']),
            Replies::webhooks(['w2']),
        );
        $result = (new WebhookCheck(Http::connections($http), self::kit()->factory))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [
                Status::Success,
                'Mailchimp calls the webhook for every binding.',
                '/3.0/lists/' . People::LIST . '/webhooks',
            ],
            [$result->status, $result->message, $http->requests[0]->getUri()->getPath()],
        );
    }

    #[Test]
    public function warnsOfEachWebhookThatDoesNotReachTheSite(): void
    {
        $kit = new SyncKit([
            ...SyncKit::NEWSLETTER,
            ...SyncKit::MEMBERS,
            'staff'  => ['list' => 'staff-list'],
            'alumni' => ['list' => 'alumni-list'],
        ]);
        $kit->webhooks->save(new RegisteredWebhook('newsletter', 'w1', null, $kit->clock->now()));
        $kit->webhooks->save(new RegisteredWebhook('members', 'w2', null, $kit->clock->now()));
        $kit->webhooks->save(new RegisteredWebhook('staff', 'w3', null, $kit->clock->now()));
        $http = new FakeHttpClient(
            Replies::webhooks(['w1']),
            Replies::webhooks([]),
            Replies::error(404, 'Resource Not Found', 'The requested resource could not be found.'),
        );
        $result = (new WebhookCheck(Http::connections($http), $kit->factory))->check(
            Contexts::make(Settings::connected()),
        );

        static::assertSame(
            [
                Status::Warning,
                'Unsubscribes and cleaned addresses don\'t reach the site: the webhook for members is no longer at'
                    . ' Mailchimp; the webhooks for staff couldn\'t be read: The requested resource could not be found.;'
                    . ' the webhook for alumni isn\'t registered. Press Re-register webhooks in the Mailchimp settings.',
            ],
            [$result->status, $result->message],
        );
    }
}
