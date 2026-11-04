<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Service\PersonStatus;
use Contenir\Extension\Mailchimp\Service\Member;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function md5;

#[Group('unit')]
final class MemberTest extends TestCase
{
    #[Test]
    public function addsANewcomerWithoutChangingTheStatusOfSomeoneAlreadyThere(): void
    {
        static::assertSame(
            ['email_address' => 'ada@example.com', 'status_if_new' => 'subscribed', 'merge_fields' => []],
            Member::subscribe(People::person()),
        );
    }

    #[Test]
    public function asksANewcomerToConfirmWhenTheyMust(): void
    {
        static::assertSame(
            'pending',
            Member::subscribe(People::person(status: PersonStatus::Pending))['status_if_new'],
        );
    }

    #[Test]
    public function identifiesAMemberByTheHashOfTheirAddress(): void
    {
        static::assertSame(md5('ada@example.com'), Member::hash(People::person(' Ada@Example.com ')));
    }

    #[Test]
    public function recordsWhenAndWhereTheySignedUp(): void
    {
        $body = Member::subscribe(People::person(consent: new Consent(
            'Signing up to the newsletter',
            ConsentSource::Form,
            new DateTimeImmutable('2026-11-04 09:30:00 UTC'),
            '203.0.113.7',
        )));

        static::assertSame(
            ['2026-11-04T09:30:00+00:00', '203.0.113.7'],
            [$body['timestamp_signup'] ?? null, $body['ip_signup'] ?? null],
        );
    }

    #[Test]
    public function recordsWhenTheySignedUpWithoutAnAddressItWasntGiven(): void
    {
        $body = Member::subscribe(People::person(consent: new Consent(
            'Signing up to the newsletter',
            ConsentSource::Record,
            new DateTimeImmutable('2026-11-04 09:30:00 UTC'),
        )));

        static::assertSame(['2026-11-04T09:30:00+00:00', false], [
            $body['timestamp_signup'] ?? null,
            array_key_exists('ip_signup', $body),
        ]);
    }

    #[Test]
    public function sendsTextAndNumbersAsTheyAre(): void
    {
        static::assertSame(
            ['merge_fields' => ['FNAME' => 'Ada', 'AGE' => 36, 'SCORE' => 9.5]],
            Member::fields(People::person(values: ['FNAME' => 'Ada', 'AGE' => 36, 'SCORE' => 9.5])),
        );
    }

    #[Test]
    public function sendsYesOrNoAsTextAndClearsAnEmptyField(): void
    {
        static::assertSame(
            ['merge_fields' => ['MEMBER' => 'Yes', 'STAFF' => 'No', 'PHONE' => '']],
            Member::fields(People::person(values: ['MEMBER' => true, 'STAFF' => false, 'PHONE' => null])),
        );
    }

    #[Test]
    public function takesBackSomeoneWhoAgreedAfresh(): void
    {
        $body = Member::subscribe(People::person(
            status: PersonStatus::Pending,
            resubscribe: true,
        ));

        static::assertSame(['pending', 'pending'], [$body['status_if_new'], $body['status'] ?? null]);
    }
}
