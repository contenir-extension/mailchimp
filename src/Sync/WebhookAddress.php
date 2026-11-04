<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Psr\Container\ContainerExceptionInterface;
use SensitiveParameter;

use function http_build_query;
use function rtrim;
use function sprintf;

/**
 * The address Mailchimp calls for one binding: the add-on's events webhook on the site's
 * own domain, which sends /api/extension/ to the CMS, with the binding and the secret in its
 * query, since the CMS routes no deeper than the webhook's name.
 *
 * @api
 */
final class WebhookAddress
{
    /**
     * The webhook's name, as the add-on registers it.
     */
    public const string NAME = 'events';

    public const string BINDING = 'binding';

    public const string KEY = 'key';

    /**
     * The address, or null when the site's own address (site.url) isn't set.
     *
     * @throws ContainerExceptionInterface When the host can't give its site settings.
     */
    public static function for(
        ExtensionContextInterface $context,
        string $binding,
        #[SensitiveParameter]
        string $secret,
    ): ?string {
        $services = $context->services();
        $settings = $services->has(SiteSettingsInterface::class) ? $services->get(SiteSettingsInterface::class) : null;
        $site     = $settings instanceof SiteSettingsInterface ? (string) $settings->get('site.url') : '';
        if ('' === $site) {
            return null;
        }

        return sprintf(
            '%s/api/extension/%s/%s?%s',
            rtrim($site, characters: '/'),
            $context->handle()->value,
            self::NAME,
            http_build_query([self::BINDING => $binding, self::KEY => $secret]),
        );
    }
}
