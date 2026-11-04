<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Factory;

use Contenir\Extension\Mailchimp\Service\Api;
use Contenir\Extension\Mailchimp\Service\Connection;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * An API client over a fake HTTP client, signing in with a test key in data centre us21.
 */
final class Apis
{
    public const string BASE = 'https://us21.api.mailchimp.com/3.0/';

    public const string KEY = '0123456789abcdef-us21';

    public static function over(FakeHttpClient $http): Api
    {
        $factory = new Psr17Factory();

        return new Api(new Connection(self::KEY), $http, $factory, $factory);
    }
}
