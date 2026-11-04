<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use Contenir\Extension\Api\Context\ExtensionContextInterface;

/**
 * Makes the add-on's sync for the site an extension context belongs to.
 *
 * @api
 */
interface SyncFactoryInterface
{
    /**
     * @throws NotReady When the site isn't set up to sync yet.
     */
    public function of(ExtensionContextInterface $context): Sync;
}
