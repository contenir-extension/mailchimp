<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Integration;

use Contenir\Extension\Mailchimp\Tests\TestAsset\Database\SiteDatabase;
use PhpDb\Migration\SchemaInspector;
use PhpDb\Sqlite\Metadata\Source;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * The add-on's migrations, as the host's runner runs them on the site's database.
 */
#[Group('integration')]
final class MigrationsTest extends TestCase
{
    #[Test]
    public function createsTheLibrarysTablesUnderTheAddOnsPrefix(): void
    {
        $adapter = SiteDatabase::adapter();
        SiteDatabase::migrations($adapter)->runPending();
        $inspector = new SchemaInspector($adapter, new Source($adapter));

        static::assertSame(
            [true, true, true, true, true],
            [
                $inspector->tableExists('ext_mailchimp_outbox'),
                $inspector->tableExists('ext_mailchimp_subject'),
                $inspector->tableExists('ext_mailchimp_suppression'),
                $inspector->tableExists('ext_mailchimp_consent'),
                $inspector->tableExists('ext_mailchimp_webhook'),
            ],
        );
    }

    #[Test]
    public function runsEachOnceInVersionOrder(): void
    {
        $adapter = SiteDatabase::adapter();
        $first   = SiteDatabase::migrations($adapter)->runPending();

        static::assertSame(
            [
                [
                    '20261105000100',
                    '20261105000200',
                    '20261105000300',
                    '20261105000400',
                    '20261105000500',
                ],
                [],
            ],
            [array_column($first, 'version'), SiteDatabase::migrations($adapter)->runPending()],
        );
    }
}
