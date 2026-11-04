<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Integration\Sync;

use Contenir\Extension\Api\Handle;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\SiteConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * The add-on's config file, read from sites kept as fixtures.
 */
#[Group('integration')]
final class SiteConfigTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedProvider(): array
    {
        return [
            'a file that is not a list'    => [
                'not-a-list',
                'mailchimp.global.php must return [\'extensions\' => [\'mailchimp\' => [...]]].',
            ],
            'its own part not a list'      => [
                'not-its-own',
                'mailchimp.global.php must return [\'extensions\' => [\'mailchimp\' => [...]]].',
            ],
            'bindings that are not a list' => [
                'bad-bindings',
                'The bindings in the add-on\'s config file must be a list.',
            ],
        ];
    }

    private static function read(string $site): SiteConfig
    {
        return SiteConfig::read(
            dirname(__DIR__, levels: 2) . "/TestAsset/Site/{$site}",
            new Handle('mailchimp'),
        );
    }

    #[Test]
    public function haveNothingWhenTheSiteHasNoFile(): void
    {
        static::assertEquals(
            new SiteConfig(null, []),
            SiteConfig::read('/no/such/site', new Handle('mailchimp')),
        );
    }

    #[Test]
    public function laysTheLocalFileOverTheGlobalOne(): void
    {
        static::assertEquals(
            new SiteConfig('a pepper for the add-on tests, 32 bytes or more', [
                'newsletter' => ['list' => 'a1b2c3d4e5f6', 'fields' => ['Name' => 'name'], 'consent' => 'Signing up'],
                'members'    => [
                    'list'    => 'members-test-list',
                    'source'  => 'library.members.member',
                    'consent' => 'Members who tick Newsletter',
                    'options' => ['consent_field' => 'newsletter'],
                ],
            ]),
            self::read('full'),
        );
    }

    #[Test]
    public function readsAnEmptyPepperAsNone(): void
    {
        static::assertNull(self::read('empty-pepper')->pepper);
    }

    #[Test]
    public function readsOnlyItsOwnAddOnsPart(): void
    {
        static::assertEquals(new SiteConfig(null, []), self::read('other-addon'));
    }

    #[DataProvider('refusedProvider')]
    #[Test]
    public function refusesAFileItCannotRead(string $site, string $message): void
    {
        $this->expectException(NotReady::class);
        $this->expectExceptionMessage($message);

        self::read($site);
    }
}
