<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Schema;

use Contenir\Audience\Schema\AbstractCreateConsent;
use Contenir\Extension\Mailchimp\MailchimpExtension;
use Override;

/**
 * The library's consent table under the add-on's prefix.
 *
 * @internal Run by the host's migration runner.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261105000400CreateConsent extends AbstractCreateConsent
{
    #[Override]
    public function getVersion(): string
    {
        return '20261105000400';
    }

    #[Override]
    protected function prefix(): string
    {
        return MailchimpExtension::PREFIX;
    }
}
