<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

use Contenir\Audience\Service\ConnectionRefused;
use SensitiveParameter;

use function base64_encode;
use function preg_match;
use function strrchr;
use function substr;

/**
 * What reaches an account's audiences at Mailchimp: an API key, which ends with the data centre
 * the account lives in, such as "-us21".
 *
 * @api
 */
final readonly class Connection
{
    public string $base;

    /**
     * @throws ConnectionRefused When the key doesn't end with its data centre.
     */
    public function __construct(
        #[SensitiveParameter]
        private string $apiKey,
    ) {
        $centre = substr((string) strrchr($apiKey, needle: '-'), offset: 1);
        if (1 !== preg_match('/^[a-z]+[0-9]+$/', $centre)) {
            throw ConnectionRefused::because('a Mailchimp API key ends with its data centre, such as -us21');
        }

        $this->base = "https://{$centre}.api.mailchimp.com/3.0/";
    }

    /**
     * The Authorization header: any user, with the key as the password, as Mailchimp asks.
     */
    public function authorization(): string
    {
        return 'Basic ' . base64_encode("contenir:{$this->apiKey}");
    }
}
