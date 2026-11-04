<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Integration;

use Contenir\Extension\Api\Conformance\AbstractAddOnConformanceTestCase;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Mailchimp\MailchimpExtension;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;

use function dirname;

/**
 * The API's own checks for an add-on: it registers cleanly, its boundary file is current, and
 * its events webhook refuses a call it can't verify.
 */
#[Group('integration')]
final class MailchimpExtensionTest extends AbstractAddOnConformanceTestCase
{
    #[Override]
    protected function extension(): ExtensionInterface
    {
        return new MailchimpExtension();
    }

    #[Override]
    protected function handle(): string
    {
        return 'mailchimp';
    }

    #[Override]
    protected function http(): Psr17Factory
    {
        return new Psr17Factory();
    }

    #[Override]
    protected function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
