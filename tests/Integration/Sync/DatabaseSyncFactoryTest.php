<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Integration\Sync;

use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\UnreachableServices;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Database\SiteDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function dirname;

/**
 * The sync as the CMS runs it, over the add-on's tables on SQLite and its config file.
 */
#[Group('integration')]
final class DatabaseSyncFactoryTest extends TestCase
{
    /**
     * @return array<string, array{array<string, object>, string}>
     */
    public static function noDatabaseProvider(): array
    {
        return [
            'none lent'      => [[], 'The CMS lends no site database as "extensions.database.site".'],
            'not an adapter' => [[DatabaseSyncFactory::DATABASE => new stdClass()], 'The CMS lends no site database'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function notReadyProvider(): array
    {
        return [
            'no pepper'        => [
                'other-addon',
                'The add-on\'s config file has no pepper to hash email addresses with.',
            ],
            'a short pepper'   => [
                'short-pepper',
                'The pepper in the add-on\'s config file must be at least 32 bytes.',
            ],
            'a binding unread' => ['unreadable-binding', 'has unknown keys: lsit'],
            'a file unread'    => ['not-a-list', 'must return'],
        ];
    }

    private static function site(string $name): string
    {
        return dirname(__DIR__, levels: 2) . "/TestAsset/Site/{$name}";
    }

    #[DataProvider('notReadyProvider')]
    #[Test]
    public function isNotReadyUntilTheConfigFileIsRight(string $site, string $message): void
    {
        $this->expectException(NotReady::class);
        $this->expectExceptionMessage($message);

        (new DatabaseSyncFactory())->of(Contexts::make(
            services: [DatabaseSyncFactory::DATABASE => SiteDatabase::adapter()],
            sitePath: self::site($site),
        ));
    }

    #[Test]
    public function isNotReadyWhenTheHostCannotMakeTheDatabase(): void
    {
        $this->expectException(NotReady::class);
        $this->expectExceptionMessage('The site\'s database can\'t be reached: refused');

        (new DatabaseSyncFactory())->of(Contexts::make(
            services: new UnreachableServices(),
            sitePath: self::site('full'),
        ));
    }

    /**
     * @param array<string, object> $services
     */
    #[DataProvider('noDatabaseProvider')]
    #[Test]
    public function isNotReadyWithoutTheSitesDatabase(array $services, string $message): void
    {
        $this->expectException(NotReady::class);
        $this->expectExceptionMessage($message);

        (new DatabaseSyncFactory())->of(Contexts::make(
            services: $services,
            sitePath: self::site('full'),
        ));
    }

    #[Test]
    public function keepsEverythingInTheAddOnsTables(): void
    {
        $adapter = SiteDatabase::adapter();
        SiteDatabase::migrations($adapter)->runPending();
        $sync = (new DatabaseSyncFactory())->of(Contexts::make(
            services: [DatabaseSyncFactory::DATABASE => $adapter],
            sitePath: self::site('full'),
        ));

        $sync->audience()->subscribe(
            'newsletter',
            ['email' => 'ada@example.com', 'name' => 'Ada'],
            new Consent('Signing up', ConsentSource::Form, $sync->clock->now()),
        );
        $sync->webhooks->save(new RegisteredWebhook('newsletter', 'w1', null, $sync->clock->now()));

        static::assertSame(
            [1, 'w1', OnDelete::Archive],
            [
                $sync->stores->outbox->counts()[OutboxState::Waiting->value],
                $adapter->query('SELECT remote_id FROM ext_mailchimp_webhook')
                    ->execute()
                    ->current()['remote_id'],
                $sync->bindings->get('members')->onDelete,
            ],
        );
    }
}
