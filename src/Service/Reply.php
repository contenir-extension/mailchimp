<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

/**
 * What Mailchimp answered to one request: its status, its decoded JSON body, and how long it
 * asked to be left alone, if it did. A request that never reached it has status 0.
 *
 * @api
 */
final readonly class Reply
{
    public function __construct(
        public int $status,
        public mixed $body = null,
        public ?int $retryAfter = null,
        private string $failure = '',
    ) {}

    /**
     * Why the request didn't succeed: Mailchimp's detail, else its title.
     */
    public function message(): string
    {
        foreach (['detail', 'title'] as $key) {
            $message = Json::string($this->body, $key);
            if ('' !== $message) {
                return $message;
            }
        }

        return '' === $this->failure ? "Mailchimp answered {$this->status}" : $this->failure;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Whether trying again later may succeed: it was busy, failed, or couldn't be reached.
     */
    public function retriable(): bool
    {
        return 0 === $this->status || 429 === $this->status || $this->status >= 500;
    }

    /**
     * Mailchimp's name for the error, such as "Member In Compliance State".
     */
    public function title(): string
    {
        return Json::string($this->body, 'title');
    }
}
