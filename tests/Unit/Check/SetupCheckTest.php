<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\CopyText;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Mailchimp\Check\SetupCheck;
use Contenir\Extension\Mailchimp\Sync\Secrets;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Context\Contexts;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\FakeSyncFactory;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[Group('unit')]
final class SetupCheckTest extends TestCase
{
    #[Test]
    public function countsTheBindingsSet(): void
    {
        $one  = (new SetupCheck((new SyncKit())->factory))->check(Contexts::make());
        $both = (new SetupCheck((new SyncKit([...SyncKit::NEWSLETTER, ...SyncKit::MEMBERS]))->factory))->check(
            Contexts::make(),
        );

        static::assertSame(
            [
                [Status::Success, '1 binding set.'],
                [Status::Success, '2 bindings set.'],
            ],
            [[$one->status, $one->message], [$both->status, $both->message]],
        );
    }

    #[Test]
    public function failsWithAFreshPepperToCopyUntilTheSiteIsSetUp(): void
    {
        $result = (new SetupCheck(
            FakeSyncFactory::notReady('No pepper.'),
            new Secrets(static fn(int $bytes): string => str_repeat("\x01", $bytes)),
        ))->check(Contexts::make());
        $fix = $result->fix;

        static::assertEquals(
            [
                Status::Failure,
                'No pepper.',
                'How to set up the config file',
                [new CopyText('A new pepper', str_repeat('01', times: 32))],
                'The site\'s config/autoload/extensions/mailchimp.global.php holds the bindings, and'
                    . ' mailchimp.local.php the pepper, each as [\'extensions\' => [\'mailchimp\' =>'
                    . ' [...]]]. The pepper must be the same for the CMS and the site, and never change once anyone is'
                    . ' synced.',
            ],
            [
                $result->status,
                $result->message,
                $fix instanceof GuidedFix ? $fix->label : null,
                $fix instanceof GuidedFix ? $fix->copy : null,
                $fix instanceof GuidedFix ? $fix->explanation : null,
            ],
        );
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Mailchimp setup', (new SetupCheck())->label());
    }

    #[Test]
    public function warnsWhenNoBindingsAreSet(): void
    {
        $result = (new SetupCheck((new SyncKit([]))->factory))->check(Contexts::make());

        static::assertSame(
            [Status::Warning, 'The add-on\'s config file sets no bindings, so nothing is synced.'],
            [$result->status, $result->message],
        );
    }
}
