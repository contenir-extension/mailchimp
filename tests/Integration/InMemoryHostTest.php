<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\Mailchimp\Check\WebhookCheck;
use Contenir\Extension\Mailchimp\MailchimpExtension;
use Contenir\Extension\Mailchimp\MailchimpSection;
use Contenir\Extension\Mailchimp\Operation\RegisterWebhooks;
use Contenir\Extension\Mailchimp\Operation\SyncNow;
use Contenir\Extension\Mailchimp\Operation\TestConnection;
use Contenir\Extension\Mailchimp\Schedule\Drain;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Database\SiteDatabase;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Http;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Mailchimp\Webhook\EventsWebhook;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function dirname;
use function http_build_query;
use function md5;

/**
 * The add-on on the API's in-memory host, over its tables on SQLite and a site's config file:
 * a bound section's save going through the outbox to Mailchimp, the webhook suppressing
 * whoever left, and its buttons, screens and checks as the CMS runs them. Mailchimp is a
 * fake HTTP client.
 */
#[Group('integration')]
final class InMemoryHostTest extends TestCase
{
    private const string SECTION = 'library.members.member';

    private const array SETTINGS = ['settings.integration.mailchimp'];

    private AdapterInterface $database;

    private FakeHttpClient $mailchimp;

    private InMemoryHost $host;

    #[Test]
    public function checksTheWebhooksAgainstMailchimp(): void
    {
        static::assertSame(
            Status::Warning,
            $this->host->check('mailchimp', 'webhooks')->status,
        );
    }

    #[Test]
    public function refusesTheButtonsToSomeoneWhoMayOnlyView(): void
    {
        static::assertNull($this->host->operate(
            'mailchimp',
            'mailchimp',
            'sync-now',
            [],
            ['settings.integration.mailchimp:view'],
        ));
    }

    #[Test]
    public function registersTheWebhooksFromItsButton(): void
    {
        $this->answer(Replies::webhook('w1', Settings::SIGNING), Replies::webhook('w2', Settings::OTHER_SIGNING));

        $result = $this->host->operate('mailchimp', 'mailchimp', 'register-webhooks', [], self::SETTINGS);

        static::assertSame(
            [
                ['Registered the webhook for newsletter.', 'Registered the webhook for members.'],
                [
                    ['binding' => 'members', 'remote_id' => 'w2', 'signing_secret' => Settings::OTHER_SIGNING],
                    ['binding' => 'newsletter', 'remote_id' => 'w1', 'signing_secret' => Settings::SIGNING],
                ],
            ],
            [
                [$result?->messages()[0]['text'], $result?->messages()[1]['text']],
                $this->rows('SELECT binding, remote_id, signing_secret FROM ext_mailchimp_webhook ORDER BY binding'),
            ],
        );
    }

    #[Test]
    public function sendsABoundSectionsSaveThroughTheOutbox(): void
    {
        $this->host->bind(self::SECTION, 'mailchimp', 'sync-record', 'RecordPostSave');
        $this->host->save(self::SECTION, null, ['email' => 'ada@example.com', 'newsletter' => '1', 'name' => 'Ada']);
        $queued = $this->rows('SELECT binding, action, email, state FROM ext_mailchimp_outbox');
        $this->answer(Replies::member('m1'));

        (new Drain(Http::connections($this->mailchimp)))->run($this->host->context('mailchimp'));

        static::assertSame(
            [
                [['binding' => 'members', 'action' => 'subscribe', 'email' => 'ada@example.com', 'state' => 'waiting']],
                '/3.0/lists/members-test-list/members/' . md5('ada@example.com'),
                [['state' => 'done', 'email' => null]],
            ],
            [
                $queued,
                $this->mailchimp->requests[0]->getUri()->getPath(),
                $this->rows('SELECT state, email FROM ext_mailchimp_outbox'),
            ],
        );
    }

    #[Test]
    public function showsTheSyncScreen(): void
    {
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest('GET', '/admin/extension/mailchimp/sync'),
            ['extension.mailchimp.sync'],
        );

        static::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function suppressesWhoeverLeftThroughTheWebhook(): void
    {
        $http     = new Psr17Factory();
        $response = $this->host->handle(
            $http->createServerRequest(
                'POST',
                'https://www.example.com/api/extension/mailchimp/events?binding=newsletter&key='
                    . Settings::SECRET,
            )
                ->withQueryParams(['binding' => 'newsletter', 'key' => Settings::SECRET])
                ->withBody($http->createStream(http_build_query([
                    'type'     => 'unsubscribe',
                    'fired_at' => '2026-11-05 09:00:00',
                    'data'     => ['action' => 'unsub', 'email' => 'ada@example.com', 'list_id' => 'a1b2c3d4e5f6'],
                ]))),
        );

        static::assertSame(
            [200, [['binding' => 'newsletter', 'reason' => 'unsubscribed']]],
            [$response->getStatusCode(), $this->rows('SELECT binding, reason FROM ext_mailchimp_suppression')],
        );
    }

    #[Test]
    public function testsTheConnectionFromItsButton(): void
    {
        $this->answer(Replies::account(), Replies::lists(['members-test-list' => 'Members (test)']));

        static::assertSame(
            'Connected to Sector7g. Its audiences: Members (test).',
            $this->host
                ->operate('mailchimp', 'mailchimp', 'test-connection', [], self::SETTINGS)
                ?->messages()[0]['text'],
        );
    }

    protected function setUp(): void
    {
        $this->database = SiteDatabase::adapter();
        SiteDatabase::migrations($this->database)->runPending();
        $this->mailchimp = new FakeHttpClient();
        $connections     = Http::connections($this->mailchimp);
        $http            = new Psr17Factory();
        $this->host      = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')),
            sitePath: dirname(__DIR__) . '/TestAsset/Site/full',
        );
        $this->host->boot(
            ['mailchimp' => new MailchimpExtension()],
            ['mailchimp'],
            [
                DatabaseSyncFactory::DATABASE => $this->database,
                SiteSettingsInterface::class  => new MemorySiteSettings(['site' => [
                    'url' => 'https://www.example.com',
                ]]),
                TestConnection::class         => new TestConnection($connections),
                SyncNow::class                => new SyncNow($connections),
                RegisterWebhooks::class       => new RegisterWebhooks($connections),
                EventsWebhook::class          => new EventsWebhook($connections),
                WebhookCheck::class           => new WebhookCheck($connections),
            ],
        );
        $this->host
            ->context('mailchimp')
            ->settings()
            ->set([
                MailchimpSection::API_KEY        => Apis::KEY,
                MailchimpSection::WEBHOOK_SECRET => Settings::SECRET,
            ]);
    }

    private function answer(ResponseInterface ...$answers): void
    {
        $this->mailchimp->answer(...$answers);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rows(string $sql): array
    {
        $rows = [];
        foreach ($this->database->query($sql)->execute() as $row) {
            $rows[] = $row;
        }

        return $rows;
    }
}
