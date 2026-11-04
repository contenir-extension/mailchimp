<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Listener;

use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Extension\Api\Event\Change;
use Contenir\Extension\Mailchimp\Listener\RecordChange;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;

#[Group('unit')]
final class RecordChangeTest extends TestCase
{
    private const string ADA = 'ada@example.com';

    private const string NEW = 'ada@new.example.com';

    /**
     * Saves with a consent checkbox: before, after, and what each queues.
     *
     * @return array<string, array{array<string, mixed>, array<string, mixed>, list<string>}>
     */
    public static function checkboxProvider(): array
    {
        return [
            'a new record, ticked'           => [
                [],
                ['email' => self::ADA, 'newsletter' => '1'],
                ['subscribe ada@example.com with consent'],
            ],
            'a new record, not ticked'       => [[], ['email' => self::ADA, 'newsletter' => '0'], []],
            'ticked now'                     => [
                ['email' => self::ADA, 'newsletter' => '0'],
                ['email' => self::ADA, 'newsletter' => 'yes'],
                ['subscribe ada@example.com with consent'],
            ],
            'unticked now'                   => [
                ['email' => self::ADA, 'newsletter' => true],
                ['email' => self::ADA, 'newsletter' => false],
                ['remove ada@example.com'],
            ],
            'still ticked'                   => [
                ['email' => self::ADA, 'newsletter' => 1, 'name' => 'A'],
                ['email' => self::ADA, 'newsletter' => 1, 'name' => 'Ada'],
                ['update ada@example.com'],
            ],
            'still not ticked'               => [['email' => self::ADA], ['email' => self::ADA, 'name' => 'Ada'], []],
            'a new address while ticked'     => [
                ['email' => self::ADA, 'newsletter' => 'on'],
                ['email' => self::NEW, 'newsletter' => 'on'],
                ['remove ada@example.com', 'subscribe ada@new.example.com with consent'],
            ],
            'a new address while not ticked' => [
                ['email' => self::ADA, 'newsletter' => 'true'],
                ['email' => self::NEW, 'newsletter' => 'no'],
                ['remove ada@example.com'],
            ],
            'the address cleared'            => [
                ['email' => self::ADA, 'newsletter' => 'Yes'],
                ['email' => ' ', 'newsletter' => 'Yes'],
                ['remove ada@example.com'],
            ],
            'no address at all'              => [[], ['newsletter' => '1'], []],
            'an address that is not text'    => [[], ['email' => 42, 'newsletter' => '1'], []],
        ];
    }

    /**
     * Saves where being in the section is the consent.
     *
     * @return array<string, array{array<string, mixed>, array<string, mixed>, list<string>}>
     */
    public static function sectionProvider(): array
    {
        return [
            'a new record'     => [[], ['email' => self::ADA], ['subscribe ada@example.com with consent']],
            'an edited record' => [
                ['email' => self::ADA, 'name' => 'A'],
                ['email' => self::ADA, 'name' => 'Ada'],
                ['update ada@example.com'],
            ],
            'a new address'    => [
                ['email' => self::ADA],
                ['email' => self::NEW],
                ['remove ada@example.com', 'subscribe ada@new.example.com with consent'],
            ],
        ];
    }

    /**
     * What the outbox holds, each message in words.
     *
     * @return list<string>
     */
    private static function queued(SyncKit $kit): array
    {
        return array_values(array_map(
            static fn(OutboxRow $row): string => (
                $row->action->value
                . ' '
                . (string) $row->email?->value
                . (null === $row->consent ? '' : ' with consent')
            ),
            $kit->stores->outbox->rows,
        ));
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private static function save(array $config, array $before, array $after): SyncKit
    {
        $kit     = new SyncKit($config);
        $binding = $kit->sync->bindings->all()[0];
        (new RecordChange($binding, new Change($before, $after)))->queue($kit->sync->audience(), $kit->clock->now());

        return $kit;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $queued
     */
    #[DataProvider('checkboxProvider')]
    #[Test]
    public function followsTheConsentCheckbox(array $before, array $after, array $queued): void
    {
        static::assertSame($queued, self::queued(self::save(SyncKit::MEMBERS, $before, $after)));
    }

    #[Test]
    public function onlyUpdatesWhenTheBindingHasNoConsentWording(): void
    {
        $kit = self::save(
            ['members' => ['list' => People::LIST, 'source' => 'library.members.member']],
            [],
            ['email' => self::ADA],
        );

        static::assertSame(['update ada@example.com'], self::queued($kit));
    }

    #[Test]
    public function recordsTheBindingsWordingAsConsentFromTheRecord(): void
    {
        $kit = self::save(SyncKit::MEMBERS, [], ['email' => self::ADA, 'newsletter' => '1', 'name' => 'Ada']);
        $row = $kit->stores->outbox->rows[1];

        static::assertSame(
            ['Members who tick Newsletter', 'record', $kit->clock->now()->format('c'), ['Name' => 'Ada']],
            [$row->consent?->wording, $row->consent?->source->value, $row->consent?->at->format('c'), $row->values],
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $queued
     */
    #[DataProvider('sectionProvider')]
    #[Test]
    public function takesBeingInTheSectionAsConsentWithoutACheckbox(array $before, array $after, array $queued): void
    {
        $config = ['members' => [...SyncKit::MEMBERS['members'], 'options' => []]];

        static::assertSame($queued, self::queued(self::save($config, $before, $after)));
    }
}
