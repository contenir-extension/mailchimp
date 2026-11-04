<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use Contenir\Audience\Audience;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\RemoteEvents;
use Contenir\Audience\Stores;
use Contenir\Audience\Webhook\WebhookStoreInterface;
use Contenir\Audience\Worker\OutboxWorker;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Everything the add-on keeps a site's lists in step with: its bindings, its tables, the webhooks
 * it registered, and the clock and log it works with.
 *
 * @api
 */
final readonly class Sync
{
    public function __construct(
        public Bindings $bindings,
        public Stores $stores,
        public WebhookStoreInterface $webhooks,
        public ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * Where sign-ups and record changes go in.
     */
    public function audience(): Audience
    {
        return new Audience($this->bindings, $this->stores, $this->clock);
    }

    /**
     * What applies the unsubscribes, bounces and complaints the webhook hears of.
     */
    public function remoteEvents(): RemoteEvents
    {
        return new RemoteEvents($this->stores);
    }

    /**
     * What sends the outbox.
     */
    public function worker(): OutboxWorker
    {
        return new OutboxWorker($this->bindings, $this->stores, $this->clock, $this->logger);
    }
}
