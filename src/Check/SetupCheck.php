<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\CopyText;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Mailchimp\Sync\DatabaseSyncFactory;
use Contenir\Extension\Mailchimp\Sync\NotReady;
use Contenir\Extension\Mailchimp\Sync\Secrets;
use Contenir\Extension\Mailchimp\Sync\SyncFactoryInterface;
use Override;
use Random\RandomException;

use function count;
use function sprintf;

/**
 * Whether the site is set up to sync: the add-on's config file has a pepper and bindings it can
 * read, and the site's database is lent. The fix explains the file and offers a fresh pepper.
 *
 * @api
 */
final readonly class SetupCheck implements CheckInterface
{
    public const string NAME = 'setup';

    public function __construct(
        private SyncFactoryInterface $syncs = new DatabaseSyncFactory(),
        private Secrets $secrets = new Secrets(),
    ) {}

    /**
     * @throws RandomException When no pepper can be made to offer.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        try {
            $sync = $this->syncs->of($context);
        } catch (NotReady $e) {
            return CheckResult::failure($e->getMessage(), new GuidedFix(
                'How to set up the config file',
                sprintf(
                    'The site\'s config/autoload/extensions/%1$s.global.php holds the bindings, and %1$s.local.php'
                        . ' the pepper, each as [\'extensions\' => [\'%1$s\' => [...]]]. The pepper must be the same'
                        . ' for the CMS and the site, and never change once anyone is synced.',
                    $context->handle()->value,
                ),
                [new CopyText('A new pepper', $this->secrets->make())],
            ));
        }

        $bindings = count($sync->bindings->all());

        return 0 === $bindings
            ? CheckResult::warning('The add-on\'s config file sets no bindings, so nothing is synced.')
            : CheckResult::success(sprintf('%d %s set.', $bindings, 1 === $bindings ? 'binding' : 'bindings'));
    }

    #[Override]
    public function label(): string
    {
        return 'Mailchimp setup';
    }
}
