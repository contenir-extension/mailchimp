<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Listener;

use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Extension\Api\Event\Change;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Event\Record\RecordPostDelete;
use Contenir\Extension\Api\Event\Record\RecordPostSave;
use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Mailchimp\Listener\RemoveRecord;
use Contenir\Extension\Mailchimp\Listener\SyncRecord;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;

/**
 * sync-record and remove-record, as the CMS calls them once a site binds them to a section.
 */
#[Group('unit')]
final class RecordListenersTest extends TestCase
{
    private const string SECTION = 'library.members.member';

    /**
     * @return list<string>
     */
    private static function queued(SyncKit $kit): array
    {
        return array_values(array_map(
            static fn(OutboxRow $row): string => "{$row->binding} {$row->action->value} {$row->email?->value}",
            $kit->stores->outbox->rows,
        ));
    }

    #[Test]
    public function logsAChangeItCannotSyncYet(): void
    {
        $logger = new MemoryLogger();

        (new SyncRecord(FakeSyncFactory::notReady('No pepper.')))->handle(
            new RecordPostSave(self::SECTION, 7, new Change([], ['email' => 'ada@example.com'])),
            Contexts::make(logger: $logger),
        );

        static::assertSame(
            [[
                'level'   => 'warning',
                'message' => 'A change in {section} wasn\'t synced: {reason}',
                'context' => [
                    'section' => self::SECTION,
                    'reason'  => 'No pepper.',
                ],
            ]],
            array_map(
                static fn(array $entry): array => [
                    'level'   => $entry['level'],
                    'message' => $entry['message'],
                    'context' => $entry['context'],
                ],
                $logger->entries(),
            ),
        );
    }

    #[Test]
    public function logsARecordWithAnAddressThatIsNotOne(): void
    {
        $kit    = new SyncKit(SyncKit::MEMBERS);
        $logger = new MemoryLogger();

        (new SyncRecord($kit->factory))->handle(
            new RecordPostSave(self::SECTION, 7, new Change([], ['email' => 'not an address', 'newsletter' => '1'])),
            Contexts::make(logger: $logger),
        );

        static::assertSame(
            [
                [],
                'info',
                'A record in {section} has no address {binding} can send',
                [
                    'section' => self::SECTION,
                    'binding' => 'members',
                ],
            ],
            [
                self::queued($kit),
                $logger->entries()[0]['level'],
                $logger->entries()[0]['message'],
                $logger->entries()[0]['context'],
            ],
        );
    }

    #[Test]
    public function passesOverAnEventItDoesNotHear(): void
    {
        $kit     = new SyncKit(SyncKit::MEMBERS);
        $context = Contexts::make();
        $change  = new Change(['email' => 'ada@example.com'], []);

        (new SyncRecord($kit->factory))->handle(new RecordPostDelete(self::SECTION, 7, $change), $context);
        (new RemoveRecord($kit->factory))->handle(new RecordPostSave(self::SECTION, 7, $change), $context);
        (new RemoveRecord($kit->factory))->handle(new RecordPreSave(self::SECTION, 7, $change), $context);

        static::assertSame([], self::queued($kit));
    }

    #[Test]
    public function passesOverASaveThatFailed(): void
    {
        $kit = new SyncKit(SyncKit::MEMBERS);

        (new SyncRecord($kit->factory))->handle(
            new RecordPostSave(
                self::SECTION,
                7,
                new Change([], ['email' => 'ada@example.com', 'newsletter' => '1']),
                Outcome::Failed,
            ),
            Contexts::make(),
        );

        static::assertSame([], self::queued($kit));
    }

    #[Test]
    public function queuesTheRemovalOfADeletedRecordsPerson(): void
    {
        $kit = new SyncKit(SyncKit::MEMBERS);

        (new RemoveRecord($kit->factory))->handle(
            new RecordPostDelete(self::SECTION, 7, new Change(['email' => 'ada@example.com'], [])),
            Contexts::make(),
        );

        static::assertSame(['members remove ada@example.com'], self::queued($kit));
    }

    #[Test]
    public function queuesTheSaveForEachBindingOfTheSectionOnly(): void
    {
        $kit = new SyncKit([
            ...SyncKit::NEWSLETTER,
            ...SyncKit::MEMBERS,
            'staff' => ['list' => 'staff-list', 'source' => self::SECTION, 'email' => 'work_email'],
        ]);

        (new SyncRecord($kit->factory))->handle(
            new RecordPostSave(
                self::SECTION,
                7,
                new Change([], [
                    'email'      => 'ada@example.com',
                    'work_email' => 'ada@work.example.com',
                    'newsletter' => '1',
                ]),
            ),
            Contexts::make(),
        );

        static::assertSame(
            ['members subscribe ada@example.com', 'staff update ada@work.example.com'],
            self::queued($kit),
        );
    }

    #[Test]
    public function skipsADeletedRecordWithNoAddress(): void
    {
        $kit    = new SyncKit(SyncKit::MEMBERS);
        $logger = new MemoryLogger();

        (new RemoveRecord($kit->factory))->handle(
            new RecordPostDelete(self::SECTION, 7, new Change(['email' => null], [])),
            Contexts::make(logger: $logger),
        );

        static::assertSame([[], 'info'], [self::queued($kit), $logger->entries()[0]['level']]);
    }
}
