<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Operation;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\RemoteList;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\OperationHandlerInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\Mailchimp\Connections;
use Contenir\Extension\Mailchimp\MailchimpSettings;
use Override;

use function array_map;
use function implode;
use function sprintf;

/**
 * Test connection: checks the saved key, without sending anyone, and names the account and its
 * audiences.
 *
 * @api
 */
final readonly class TestConnection implements OperationHandlerInterface
{
    public const string NAME = 'test-connection';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    #[Override]
    public function handle(OperationContextInterface $context): OperationResultInterface
    {
        $extension = $context->extension();
        if (null === $extension) {
            return OperationResult::message('danger', 'The CMS ran this without the Mailchimp add-on\'s settings.');
        }

        $settings   = MailchimpSettings::from($extension->settings());
        $connection = $settings->connection();
        if (null === $connection) {
            return OperationResult::message('warning', (string) $settings->missing());
        }

        $adapter = $this->connections->adapter($connection);
        try {
            $account   = $adapter->verifyConnection();
            $audiences = array_map(static fn(RemoteList $list): string => $list->name, $adapter->lists());
        } catch (ConnectionRefused $e) {
            return OperationResult::message('danger', $e->getMessage());
        }

        return OperationResult::message('success', sprintf(
            'Connected to %s. %s',
            $account->name,
            [] === $audiences
                ? 'It has no audiences yet.'
                : sprintf('Its audiences: %s.', implode(', ', $audiences)),
        ));
    }
}
