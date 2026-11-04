<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Audience\Service\Outcome;
use Contenir\Extension\Mailchimp\Service\Outcomes;
use Contenir\Extension\Mailchimp\Service\Reply;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OutcomesTest extends TestCase
{
    #[Test]
    public function countsSomeoneNotInTheAudienceAsAlreadyOffIt(): void
    {
        static::assertEquals(Outcome::done(), Outcomes::gone(new Reply(404, ['title' => 'Resource Not Found'])));
    }

    #[Test]
    public function failsWhatMailchimpRefused(): void
    {
        static::assertEquals(
            Outcome::failed('Please provide a valid email address.'),
            Outcomes::of(new Reply(400, [
                'title'  => 'Invalid Resource',
                'detail' => 'Please provide a valid email address.',
            ])),
        );
    }

    #[Test]
    public function forgetsAnAddressInAComplianceState(): void
    {
        static::assertEquals(
            Outcome::forgotten('ada@example.com is in a compliance state.'),
            Outcomes::of(new Reply(400, [
                'title'  => 'Member In Compliance State',
                'detail' => 'ada@example.com is in a compliance state.',
            ])),
        );
    }

    #[Test]
    public function forgetsAnAddressMailchimpForgot(): void
    {
        static::assertEquals(
            Outcome::forgotten('This person was permanently deleted.'),
            Outcomes::of(new Reply(400, [
                'title'  => 'Forgotten Email Not Subscribed',
                'detail' => 'This person was permanently deleted.',
            ])),
        );
    }

    #[Test]
    public function isDoneWithMailchimpsIdForThePerson(): void
    {
        static::assertEquals(Outcome::done('m1'), Outcomes::of(new Reply(200, ['id' => 'm1'])));
    }

    #[Test]
    public function isDoneWithoutAnIdWhenMailchimpGivesNone(): void
    {
        static::assertEquals(Outcome::done(), Outcomes::of(new Reply(204)));
    }

    #[Test]
    public function takesSomeoneOffTheAudienceAsAnyOtherReplyOtherwise(): void
    {
        static::assertEquals(Outcome::retry('Mailchimp answered 500'), Outcomes::gone(new Reply(500)));
    }

    #[Test]
    public function triesAgainWhenMailchimpIsBusyAfterTheWaitItAsks(): void
    {
        static::assertEquals(
            Outcome::retry('Slow down', 30),
            Outcomes::of(new Reply(429, ['title' => 'Too Many Requests', 'detail' => 'Slow down'], 30)),
        );
    }
}
