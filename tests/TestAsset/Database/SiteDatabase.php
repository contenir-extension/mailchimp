<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Database;

use Contenir\Extension\Mailchimp\MailchimpExtension;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Migration\MigrationRunner;
use PhpDb\Migration\MismatchStrategy;
use PhpDb\Sqlite;

use function dirname;

/**
 * A site database in memory, on SQLite, with the add-on's migrations to run on it.
 */
final class SiteDatabase
{
    public static function adapter(): AdapterInterface
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $driver = new Sqlite\Pdo\Driver(connection: new Sqlite\Pdo\Connection($pdo));

        return new Adapter($driver, new Sqlite\AdapterPlatform($driver));
    }

    /**
     * The add-on's migrations, as the host's runner finds them.
     */
    public static function migrations(AdapterInterface $adapter): MigrationRunner
    {
        return new MigrationRunner(
            $adapter,
            dirname(__DIR__, levels: 3) . '/src/Schema',
            MailchimpExtension::SCHEMA,
            MismatchStrategy::Report,
            new Sqlite\Metadata\Source($adapter),
        );
    }
}
