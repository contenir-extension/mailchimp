<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Listener;

use Contenir\Audience\Binding\Binding;
use Contenir\Extension\Api\Event\Record\AbstractRecordPostEvent;
use Contenir\Extension\Api\Event\Record\RecordPostSave;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Override;

/**
 * sync-record, on RecordPostSave: a saved record subscribes, updates or removes its person, as
 * {@see RecordChange} decides.
 *
 * @api
 */
final readonly class SyncRecord extends AbstractRecordListener
{
    public const string NAME = 'sync-record';

    #[Override]
    protected function hears(AbstractRecordPostEvent $event): bool
    {
        return $event instanceof RecordPostSave;
    }

    #[Override]
    protected function queue(Sync $sync, Binding $binding, AbstractRecordPostEvent $event): void
    {
        (new RecordChange($binding, $event->change()))->queue($sync->audience(), $sync->clock->now());
    }
}
