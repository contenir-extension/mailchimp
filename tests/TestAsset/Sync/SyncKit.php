<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Sync;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\Testing\InMemoryStores;
use Contenir\Audience\Testing\InMemoryWebhookStore;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Mailchimp\Sync\Sync;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;

/**
 * A sync held in memory, with its stores, webhooks, clock and log in reach of a test, and a
 * factory that gives it.
 */
final readonly class SyncKit
{
    public InMemoryStores $stores;

    public InMemoryWebhookStore $webhooks;

    public MutableClock $clock;

    public MemoryLogger $logger;

    public Sync $sync;

    public FakeSyncFactory $factory;

    /**
     * @param array<array-key, mixed> $bindings Each binding's config, by name
     */
    public function __construct(array $bindings = self::NEWSLETTER)
    {
        $this->stores   = new InMemoryStores();
        $this->webhooks = new InMemoryWebhookStore();
        $this->clock    = new MutableClock();
        $this->logger   = new MemoryLogger();
        $this->sync     = new Sync(
            Bindings::fromConfig($bindings, OnDelete::Archive),
            $this->stores->stores,
            $this->webhooks,
            $this->clock,
            $this->logger,
        );
        $this->factory = new FakeSyncFactory($this->sync);
    }

    /**
     * One binding fed by the site's sign-up forms.
     */
    public const array NEWSLETTER = [
        'newsletter' => [
            'list'    => People::LIST,
            'fields'  => ['Name' => 'name'],
            'consent' => 'Signing up to the newsletter',
        ],
    ];

    /**
     * One binding syncing the members section, consenting with its newsletter checkbox.
     */
    public const array MEMBERS = [
        'members' => [
            'list'    => People::LIST,
            'source'  => 'library.members.member',
            'fields'  => ['Name' => 'name'],
            'consent' => 'Members who tick Newsletter',
            'options' => ['consent_field' => 'newsletter'],
        ],
    ];
}
