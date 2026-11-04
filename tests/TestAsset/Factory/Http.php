<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Factory;

use Contenir\Extension\Mailchimp\Connections;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Connections to Mailchimp over a fake HTTP client.
 */
final class Http
{
    public static function connections(FakeHttpClient $http): Connections
    {
        $factory = new Psr17Factory();

        return new Connections($http, $factory, $factory);
    }
}
