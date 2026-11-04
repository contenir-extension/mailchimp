<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Listener;

use Contenir\Audience\Binding\Binding;
use Contenir\Extension\Api\Event\Record\AbstractRecordPostEvent;
use Contenir\Extension\Api\Event\Record\RecordPostDelete;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Override;

use function is_string;

/**
 * remove-record, on RecordPostDelete: a deleted record's person is removed as the binding's
 * on-delete setting says: archived, by default (MCH-1), or unsubscribed, or kept.
 *
 * @api
 */
final readonly class RemoveRecord extends AbstractRecordListener
{
    public const string NAME = 'remove-record';

    #[Override]
    protected function hears(AbstractRecordPostEvent $event): bool
    {
        return $event instanceof RecordPostDelete;
    }

    /**
     * @mago-expect analysis:mixed-assignment A record's values are untyped; the email is checked as text.
     */
    #[Override]
    protected function queue(Sync $sync, Binding $binding, AbstractRecordPostEvent $event): void
    {
        $email = $event->change()->before[$binding->emailField] ?? null;
        $sync->audience()->remove($binding->name, is_string($email) ? $email : '');
    }
}
