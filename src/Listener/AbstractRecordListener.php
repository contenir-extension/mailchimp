<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Listener;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Event\EventInterface;
use Contenir\Extension\Api\Event\Record\AbstractRecordPostEvent;
use Contenir\Extension\Api\Listener\ListenerInterface;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;

/**
 * A listener a site binds to a section in admin.xml: for each binding whose source is that
 * section, it puts what the record's change means in the outbox, and nothing is sent while the
 * editor waits. A record with no usable address is logged and left; a site not yet set up to
 * sync is logged once per change.
 *
 * @api
 */
abstract readonly class AbstractRecordListener implements ListenerInterface
{
    public function __construct(
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
    ) {}

    /**
     * @throws StorageException When the outbox can't be written.
     * @throws UnknownBinding   Never: each binding is the sync's own.
     */
    #[Override]
    final public function handle(EventInterface $event, ExtensionContextInterface $context): void
    {
        if (! $event instanceof AbstractRecordPostEvent || ! $this->hears($event) || ! $event->succeeded()) {
            return;
        }

        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady $e) {
            $context->logger()->warning('A change in {section} wasn\'t synced: {reason}', [
                'section' => $event->section,
                'reason'  => $e->getMessage(),
            ]);

            return;
        }

        foreach ($sync->bindings->forSection($event->section) as $binding) {
            try {
                $this->queue($sync, $binding, $event);
            } catch (InvalidEmail) {
                $context->logger()->info('A record in {section} has no address {binding} can send', [
                    'section' => $event->section,
                    'binding' => $binding->name,
                ]);
            }
        }
    }

    /**
     * Whether this listener acts on $event.
     */
    abstract protected function hears(AbstractRecordPostEvent $event): bool;

    /**
     * Puts what $event means for $binding in the outbox.
     *
     * @throws InvalidEmail
     * @throws StorageException
     * @throws UnknownBinding Never: the binding is the sync's own.
     */
    abstract protected function queue(Sync $sync, Binding $binding, AbstractRecordPostEvent $event): void;
}
