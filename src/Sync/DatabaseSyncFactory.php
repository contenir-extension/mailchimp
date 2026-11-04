<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\EmailHasher;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Storage\PhpDbConsentLedger;
use Contenir\Audience\Storage\PhpDbOutboxStore;
use Contenir\Audience\Storage\PhpDbSubjectStore;
use Contenir\Audience\Storage\PhpDbSuppressionStore;
use Contenir\Audience\Storage\PhpDbWebhookStore;
use Contenir\Audience\Storage\Tables;
use Contenir\Audience\Stores;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Mailchimp\MailchimpExtension;
use InvalidArgumentException;
use Override;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;

use function sprintf;

/**
 * The sync as the CMS runs it: the tables in the site's database, which the host lends add-ons
 * as extensions.database.site, and the bindings and pepper from the add-on's config file.
 * Mailchimp archives on delete (MCH-1) unless a binding says otherwise.
 *
 * @api
 */
final readonly class DatabaseSyncFactory implements SyncFactoryInterface
{
    /**
     * The service the CMS lends add-ons the site's database under.
     */
    public const string DATABASE = 'extensions.database.site';

    /**
     * @throws NotReady
     *
     * @mago-expect analysis:mixed-assignment A container's services are untyped; the adapter is checked before use.
     */
    private static function database(ExtensionContextInterface $context): AdapterInterface
    {
        try {
            $adapter = $context->services()->has(self::DATABASE) ? $context->services()->get(self::DATABASE) : null;
        } catch (ContainerExceptionInterface $e) {
            throw NotReady::because(sprintf('The site\'s database can\'t be reached: %s', $e->getMessage()));
        }

        return $adapter instanceof AdapterInterface
            ? $adapter
            : throw NotReady::because(sprintf('The CMS lends no site database as "%s".', self::DATABASE));
    }

    /**
     * @throws NotReady
     */
    private static function hasher(?string $pepper): EmailHasher
    {
        if (null === $pepper) {
            throw NotReady::because('The add-on\'s config file has no pepper to hash email addresses with.');
        }

        try {
            return new EmailHasher($pepper);
        } catch (InvalidArgumentException) {
            throw NotReady::because(sprintf(
                'The pepper in the add-on\'s config file must be at least %d bytes.',
                EmailHasher::MIN_PEPPER_BYTES,
            ));
        }
    }

    #[Override]
    public function of(ExtensionContextInterface $context): Sync
    {
        $config = SiteConfig::read($context->sitePath(), $context->handle());
        $hasher = self::hasher($config->pepper);
        try {
            $bindings = Bindings::fromConfig($config->bindings, OnDelete::Archive);
        } catch (InvalidBinding $e) {
            throw NotReady::because($e->getMessage());
        }

        $adapter = self::database($context);
        $tables  = new Tables(MailchimpExtension::PREFIX);

        return new Sync(
            $bindings,
            new Stores(
                new PhpDbOutboxStore($adapter, $tables),
                new PhpDbSubjectStore($adapter, $tables),
                new PhpDbSuppressionStore($adapter, $tables),
                new PhpDbConsentLedger($adapter, $tables),
                $hasher,
            ),
            new PhpDbWebhookStore($adapter, $tables),
            $context->clock(),
            $context->logger(),
        );
    }
}
