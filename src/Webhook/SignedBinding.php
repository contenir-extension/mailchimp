<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Webhook;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Webhook\RegisteredWebhook;

/**
 * A binding as the webhook receiver reads it: with the secret Mailchimp gave when its webhook was
 * registered as the option signing_secret, so each call's signature is checked against it.
 *
 * @api
 */
final class SignedBinding
{
    /**
     * $binding, with the registered webhook's signing secret when it has one.
     */
    public static function of(Binding $binding, ?RegisteredWebhook $webhook): Binding
    {
        $secret = $webhook?->signingSecret;
        if (null === $secret || '' === $secret) {
            return $binding;
        }

        return new Binding(
            $binding->name,
            $binding->list,
            $binding->source,
            $binding->emailField,
            $binding->fields,
            $binding->consent,
            $binding->doubleOptIn,
            $binding->tags,
            $binding->onDelete,
            [...$binding->options, MailchimpWebhooks::SECRET_OPTION => $secret],
        );
    }
}
