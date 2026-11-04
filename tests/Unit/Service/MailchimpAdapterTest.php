<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\RemoteField;
use Contenir\Audience\Service\RemoteList;
use Contenir\Extension\Mailchimp\Service\MailchimpAdapter;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Apis;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Replies;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Http\FakeHttpClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[Group('unit')]
final class MailchimpAdapterTest extends TestCase
{
    private const string MEMBERS = Apis::BASE . 'lists/' . People::LIST . '/members/';

    private static function adapter(FakeHttpClient $http): MailchimpAdapter
    {
        return new MailchimpAdapter(Apis::over($http));
    }

    /**
     * @return array{string, string, mixed}
     */
    private static function sent(RequestInterface $request): array
    {
        $body = (string) $request->getBody();

        return [
            $request->getMethod(),
            (string) $request->getUri(),
            '' === $body ? null : json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    #[Test]
    public function addsOrUpdatesEachPersonByTheirHash(): void
    {
        $http = new FakeHttpClient(
            Replies::member('m1'),
            Replies::error(400, 'Member In Compliance State', 'Compliance'),
        );
        $outcomes = self::adapter($http)
            ->subscribe(People::binding(), [People::person(), People::person('grace@example.com')]);

        static::assertEquals(
            [
                [
                    'PUT',
                    self::MEMBERS . People::hash(),
                    ['email_address' => 'ada@example.com', 'status_if_new' => 'subscribed', 'merge_fields' => []],
                ],
                [Outcome::done('m1'), Outcome::forgotten('Compliance')],
            ],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function archivesEachPerson(): void
    {
        $http     = new FakeHttpClient(Replies::deleted(), Replies::error(500, 'Internal Server Error', 'Oops'));
        $outcomes = self::adapter($http)
            ->archive(People::binding(), [People::person(), People::person('grace@example.com')]);

        static::assertEquals(
            [['DELETE', self::MEMBERS . People::hash(), null], [Outcome::done(), Outcome::retry('Oops')]],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function listsTheAccountsAudiences(): void
    {
        $http = new FakeHttpClient(Replies::json(200, [
            'lists'       => [['id' => 'l1', 'name' => 'Newsletter']],
            'total_items' => 1,
        ]));
        $lists = self::adapter($http)->lists();

        static::assertEquals(
            [Apis::BASE . 'lists?count=1000&fields=lists.id%2Clists.name', [new RemoteList('l1', 'Newsletter')]],
            [(string) $http->requests[0]->getUri(), $lists],
        );
    }

    #[Test]
    public function listsTheAudiencesMergeFields(): void
    {
        $http = new FakeHttpClient(Replies::json(200, [
            'merge_fields' => [
                ['tag' => 'FNAME', 'name' => 'First name', 'type' => 'text', 'required' => false],
                ['tag' => 'PHONE', 'name' => 'Phone', 'type' => 'phone', 'required' => true],
            ],
        ]));
        $fields = self::adapter($http)->fields('l/1');

        static::assertEquals(
            [
                Apis::BASE . 'lists/l%2F1/merge-fields?count=1000',
                [new RemoteField('FNAME', 'First name', 'text'), new RemoteField('PHONE', 'Phone', 'phone', true)],
            ],
            [(string) $http->requests[0]->getUri(), $fields],
        );
    }

    #[Test]
    public function readsAnAccountWithNothingInItAsEmpty(): void
    {
        static::assertEquals(
            new Account('', ''),
            self::adapter(new FakeHttpClient(Replies::json(200, 'odd')))->verifyConnection(),
        );
    }

    #[Test]
    public function refusesAReadThatFailed(): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: Not found');

        self::adapter(new FakeHttpClient(Replies::error(404, 'Resource Not Found', 'Not found')))->lists();
    }

    #[Test]
    public function sendsAHundredAtATimeAndAsksEachRequestAboutConfirmation(): void
    {
        static::assertEquals(
            new Capabilities(100, DoubleOptIn::PerRequest, tags: true, signedWebhooks: true),
            self::adapter(new FakeHttpClient())->capabilities(),
        );
    }

    #[Test]
    public function sendsNoTagsWhenTheBindingHasNone(): void
    {
        $http = new FakeHttpClient(Replies::member('m1'));
        self::adapter($http)->subscribe(People::binding(), [People::person()]);

        static::assertCount(1, $http->requests);
    }

    #[Test]
    public function tagsEveryoneWhoJoins(): void
    {
        $http    = new FakeHttpClient(Replies::member('m1'), Replies::deleted());
        $outcome = self::adapter($http)->subscribe(People::binding(['Website', 'Newsletter']), [People::person()]);

        static::assertEquals(
            [
                [
                    'POST',
                    self::MEMBERS . People::hash() . '/tags',
                    [
                        'tags' => [
                            ['name' => 'Website', 'status' => 'active'],
                            ['name' => 'Newsletter', 'status' => 'active'],
                        ],
                    ],
                ],
                [Outcome::done('m1')],
            ],
            [self::sent($http->requests[1]), $outcome],
        );
    }

    #[Test]
    public function tagsNoOneWhoDidntJoin(): void
    {
        $http = new FakeHttpClient(Replies::error(400, 'Invalid Resource', 'Bad address'));
        self::adapter($http)->subscribe(People::binding(['Website']), [People::person()]);

        static::assertCount(1, $http->requests);
    }

    #[Test]
    public function triesAgainWhenTaggingFailed(): void
    {
        $http    = new FakeHttpClient(Replies::member('m1'), Replies::error(503, 'Service Unavailable', 'Down'));
        $outcome = self::adapter($http)->subscribe(People::binding(['Website']), [People::person()]);

        static::assertEquals([Outcome::retry('Down')], $outcome);
    }

    #[Test]
    public function unsubscribesEachPerson(): void
    {
        $http = new FakeHttpClient(
            Replies::member('m1', 'unsubscribed'),
            Replies::error(404, 'Resource Not Found', 'Not found'),
        );
        $outcomes = self::adapter($http)
            ->unsubscribe(People::binding(), [People::person(), People::person('grace@example.com')]);

        static::assertEquals(
            [
                ['PATCH', self::MEMBERS . People::hash(), ['status' => 'unsubscribed']],
                [Outcome::done('m1'), Outcome::done()],
            ],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function updatesMergeFieldsOnly(): void
    {
        $http     = new FakeHttpClient(Replies::member('m1'), Replies::error(404, 'Resource Not Found', 'Not found'));
        $outcomes = self::adapter($http)
            ->update(People::binding(), [
                People::person(values: ['FNAME' => 'Ada']),
                People::person('grace@example.com'),
            ]);

        static::assertEquals(
            [
                ['PATCH', self::MEMBERS . People::hash(), ['merge_fields' => ['FNAME' => 'Ada']]],
                [Outcome::done('m1'), Outcome::failed('Not found')],
            ],
            [self::sent($http->requests[0]), $outcomes],
        );
    }

    #[Test]
    public function verifiesTheConnectionByReadingTheAccount(): void
    {
        $http    = new FakeHttpClient(Replies::json(200, ['account_id' => 'a1', 'account_name' => 'Sector7g']));
        $account = self::adapter($http)->verifyConnection();

        static::assertEquals(
            [Apis::BASE . '?fields=account_id%2Caccount_name', new Account('a1', 'Sector7g')],
            [(string) $http->requests[0]->getUri(), $account],
        );
    }
}
