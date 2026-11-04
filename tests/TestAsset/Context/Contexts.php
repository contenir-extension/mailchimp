<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Context;

use Contenir\Audience\Testing\MutableClock;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\InMemoryContext;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\Api\Testing\ServiceMap;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Settings\ArraySettings;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An add-on context as the CMS gives one, held in memory: the section's settings, the host's
 * services, the site at /site with its address set, a log and a clock.
 */
final class Contexts
{
    public const string SITE = 'https://www.example.com';

    /**
     * @param array<string, object>|ContainerInterface $services
     */
    public static function make(
        ArraySettings $settings = new ArraySettings(),
        array|ContainerInterface $services = [],
        MemoryLogger $logger = new MemoryLogger(),
        string $sitePath = '/site',
        MutableClock $clock = new MutableClock(),
    ): InMemoryContext {
        return new InMemoryContext(
            new Handle('mailchimp'),
            $services instanceof ContainerInterface
                ? $services
                : new ServiceMap([
                    SiteSettingsInterface::class => new MemorySiteSettings(['site' => ['url' => self::SITE . '/']]),
                    ...$services,
                ]),
            $logger,
            $clock,
            $sitePath,
            new MapUrlResolver(),
            new class implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
            static fn(): ArraySettings => $settings,
        );
    }
}
