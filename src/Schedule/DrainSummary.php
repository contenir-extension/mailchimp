<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Schedule;

use Contenir\Audience\Worker\DrainReport;

use function implode;
use function sprintf;

/**
 * One drain of the outbox in words, for Sync all now and the log.
 *
 * @api
 */
final class DrainSummary
{
    /**
     * The message's level: danger when sending paused, warning when anything failed or ran out
     * of retries, success otherwise.
     */
    public static function level(DrainReport $report): string
    {
        return match (true) {
            $report->isPaused() => 'danger',
            $report->failed > 0 || $report->dead > 0 => 'warning',
            default => 'success',
        };
    }

    /**
     * Whether the drain did anything worth logging.
     */
    public static function noteworthy(DrainReport $report): bool
    {
        return (
            $report->isPaused()
                || $report->sent > 0
                || $report->skipped > 0
                || $report->retried > 0
                || $report->failed > 0
                || $report->dead > 0
        );
    }

    public static function text(DrainReport $report): string
    {
        $parts = [sprintf(
            'Sent %d; %d needed nothing; %d will be tried again; %d failed; %d ran out of retries.',
            $report->sent,
            $report->skipped,
            $report->retried,
            $report->failed,
            $report->dead,
        )];
        if ($report->isPaused()) {
            $parts[] = sprintf('Sending paused: %s', (string) $report->paused);
        }

        if (! $report->isPaused() && ! $report->finished) {
            $parts[] = 'More is waiting, and goes in the next minute.';
        }

        return implode(' ', $parts);
    }
}
