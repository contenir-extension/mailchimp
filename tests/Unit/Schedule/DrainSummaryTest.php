<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Schedule;

use Contenir\Audience\Worker\DrainReport;
use Contenir\Extension\Mailchimp\Schedule\DrainSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DrainSummaryTest extends TestCase
{
    /**
     * @return array<string, array{DrainReport, string}>
     */
    public static function levelProvider(): array
    {
        return [
            'all sent'       => [new DrainReport(2, 0, 1, 0, 0, true, null), 'success'],
            'one failed'     => [new DrainReport(1, 0, 0, 1, 0, true, null), 'warning'],
            'one dead'       => [new DrainReport(1, 0, 0, 0, 1, true, null), 'warning'],
            'sending paused' => [new DrainReport(0, 0, 0, 1, 0, false, 'Bad key'), 'danger'],
        ];
    }

    /**
     * @return array<string, array{DrainReport, bool}>
     */
    public static function noteworthyProvider(): array
    {
        return [
            'nothing' => [new DrainReport(0, 0, 0, 0, 0, true, null), false],
            'sent'    => [new DrainReport(1, 0, 0, 0, 0, true, null), true],
            'skipped' => [new DrainReport(0, 1, 0, 0, 0, true, null), true],
            'retried' => [new DrainReport(0, 0, 1, 0, 0, true, null), true],
            'failed'  => [new DrainReport(0, 0, 0, 1, 0, true, null), true],
            'dead'    => [new DrainReport(0, 0, 0, 0, 1, true, null), true],
            'paused'  => [new DrainReport(0, 0, 0, 0, 0, false, 'Bad key'), true],
        ];
    }

    /**
     * @return array<string, array{DrainReport, string}>
     */
    public static function textProvider(): array
    {
        return [
            'finished'   => [
                new DrainReport(1, 2, 3, 4, 5, true, null),
                'Sent 1; 2 needed nothing; 3 will be tried again; 4 failed; 5 ran out of retries.',
            ],
            'unfinished' => [
                new DrainReport(1, 0, 0, 0, 0, false, null),
                'Sent 1; 0 needed nothing; 0 will be tried again; 0 failed; 0 ran out of retries. More is waiting,'
                    . ' and goes in the next minute.',
            ],
            'paused'     => [
                new DrainReport(0, 0, 0, 0, 0, false, 'Bad key'),
                'Sent 0; 0 needed nothing; 0 will be tried again; 0 failed; 0 ran out of retries. Sending paused:'
                    . ' Bad key',
            ],
        ];
    }

    #[DataProvider('levelProvider')]
    #[Test]
    public function choosesTheLevelByTheWorstOutcome(DrainReport $report, string $level): void
    {
        static::assertSame($level, DrainSummary::level($report));
    }

    #[DataProvider('noteworthyProvider')]
    #[Test]
    public function isNoteworthyWhenAnythingHappened(DrainReport $report, bool $noteworthy): void
    {
        static::assertSame($noteworthy, DrainSummary::noteworthy($report));
    }

    #[DataProvider('textProvider')]
    #[Test]
    public function saysWhatBecameOfEachMessage(DrainReport $report, string $text): void
    {
        static::assertSame($text, DrainSummary::text($report));
    }
}
