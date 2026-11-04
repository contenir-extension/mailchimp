<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Check;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Mailchimp\Connections;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Override;

use function sprintf;

/**
 * Whether Mailchimp accepts the saved key. While it refuses it,
 * nothing is sent and everything waits, so the check fails with a way to the settings.
 *
 * @api
 */
final readonly class ConnectionCheck implements CheckInterface
{
    public const string NAME = 'connection';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $settings   = MailchimpSettings::from($context->settings());
        $connection = $settings->connection();
        if (null === $connection) {
            return CheckResult::warning((string) $settings->missing());
        }

        try {
            $account = $this->connections->adapter($connection)->verifyConnection();
        } catch (ConnectionRefused $e) {
            return CheckResult::failure(
                sprintf('%s Nothing is sent until it\'s put right; everything waits.', $e->getMessage()),
                new GuidedFix(
                    'Open the connection settings',
                    'Check the API key in Site Settings > Integration > Mailchimp, save, and press Test'
                        . ' connection. Sending starts again in the next minute.',
                ),
            );
        }

        return CheckResult::success(sprintf('Connected to %s.', $account->name));
    }

    #[Override]
    public function label(): string
    {
        return 'Mailchimp connection';
    }
}
