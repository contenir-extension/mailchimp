<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Webhook;

use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Extension\Mailchimp\Service\Json;
use SensitiveParameter;

use function abs;
use function filter_var;
use function hash_equals;
use function hash_hmac;
use function parse_str;
use function str_replace;

use const FILTER_VALIDATE_INT;

/**
 * Mailchimp's signature on a webhook call: "t={unix time},v1={hex}", the HMAC-SHA256 of the time
 * and the raw body with the webhook's signing secret. A call signed more than five minutes from
 * now is refused, so a recorded call can't be replayed later.
 *
 * @api
 */
final class Signature
{
    public const string HEADER = 'X-Mailchimp-Signature';

    /** Seconds a signature stays good, either side of now */
    public const int TOLERANCE = 300;

    /**
     * @throws InvalidWebhook When the call isn't signed with $secret, or not lately.
     */
    public static function verify(
        string $header,
        string $body,
        #[SensitiveParameter]
        string $secret,
        int $now,
    ): void {
        $fields = [];
        parse_str(str_replace(
            search: ',',
            replace: '&',
            subject: $header,
        ), $fields);
        $time      = filter_var(Json::string($fields, 't'), FILTER_VALIDATE_INT);
        $signature = Json::string($fields, 'v1');
        if (false === $time || '' === $signature) {
            throw InvalidWebhook::because('it isn\'t signed');
        }

        if (abs($now - $time) > self::TOLERANCE) {
            throw InvalidWebhook::because('its signature is more than five minutes out');
        }

        if (! hash_equals(hash_hmac('sha256', "{$time}.{$body}", $secret), $signature)) {
            throw InvalidWebhook::because('its signature doesn\'t match');
        }
    }
}
