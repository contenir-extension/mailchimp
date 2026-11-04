<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Schema;

use Contenir\Audience\Schema\AbstractCreateOutbox;
use Contenir\Extension\Mailchimp\MailchimpExtension;
use Override;

/**
 * The library's outbox table under the add-on's prefix.
 *
 * @internal Run by the host's migration runner.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000100CreateOutbox extends AbstractCreateOutbox
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000100';
    }

    #[Override]
    protected function prefix(): string
    {
        return MailchimpExtension::PREFIX;
    }
}
