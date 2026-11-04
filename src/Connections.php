<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp;

use Contenir\Extension\Mailchimp\Service\Api;
use Contenir\Extension\Mailchimp\Service\Connection;
use Contenir\Extension\Mailchimp\Service\MailchimpAdapter;
use Contenir\Extension\Mailchimp\Webhook\MailchimpWebhooks;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds what talks to Mailchimp over one HTTP client: Guzzle, with the API's timeouts, unless a
 * test gives it another.
 *
 * @api
 */
final readonly class Connections
{
    public function __construct(
        private ClientInterface $http = new Guzzle(Api::GUZZLE),
        private RequestFactoryInterface $requests = new HttpFactory(),
        private StreamFactoryInterface $streams = new HttpFactory(),
    ) {}

    public function adapter(Connection $connection): MailchimpAdapter
    {
        return new MailchimpAdapter($this->api($connection));
    }

    public function api(Connection $connection): Api
    {
        return new Api($connection, $this->http, $this->requests, $this->streams);
    }

    /**
     * The webhook receiver, which checks each signature's time against $clock.
     */
    public function webhooks(Connection $connection, ClockInterface $clock): MailchimpWebhooks
    {
        return new MailchimpWebhooks($this->api($connection), $clock);
    }
}
