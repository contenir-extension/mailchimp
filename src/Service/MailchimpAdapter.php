<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\OutcomeKind;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\RemoteField;
use Contenir\Audience\Service\RemoteList;
use Contenir\Audience\Service\ServiceAdapterInterface;
use Override;

use function array_map;
use function rawurlencode;

/**
 * Mailchimp's half of keeping an audience in step: one request per person, each idempotent, so
 * a batch cut short is safe to send again. Each request asks whether the person confirms by
 * email first, and the binding's tags are added to everyone who joins.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods One method per method of the service interface, plus the requests they share.
 */
final readonly class MailchimpAdapter implements ServiceAdapterInterface
{
    /**
     * The most people one batch carries: each is a request, so a hundred keeps a batch well
     * inside the drain's time.
     */
    public const int BATCH = 100;

    /** The most lists or merge fields one read returns */
    public const string COUNT = '1000';

    public function __construct(
        private Api $api,
    ) {}

    /**
     * @return array{name: string, status: string}
     */
    private static function activeTag(string $tag): array
    {
        return ['name' => $tag, 'status' => 'active'];
    }

    private static function member(Binding $binding, Person $person): string
    {
        return 'lists/' . rawurlencode($binding->list) . '/members/' . Member::hash($person);
    }

    /**
     * Archives people: Mailchimp keeps them, out of the audience, and can add them back.
     */
    #[Override]
    public function archive(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => Outcomes::gone($this->api->send('DELETE', self::member($binding, $person))),
            $people,
        );
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return new Capabilities(self::BATCH, DoubleOptIn::PerRequest, tags: true, signedWebhooks: true);
    }

    #[Override]
    public function fields(string $listId): array
    {
        return array_map(
            /** @param array<array-key, mixed> $row */
            static fn(array $row): RemoteField => new RemoteField(
                Json::string($row, 'tag'),
                Json::string($row, 'name'),
                Json::string($row, 'type'),
                Json::bool($row, 'required'),
            ),
            Json::rows($this->read('lists/' . rawurlencode($listId) . '/merge-fields', [
                'count' => self::COUNT,
            ])['merge_fields'] ?? null),
        );
    }

    #[Override]
    public function lists(): array
    {
        return array_map(
            /** @param array<array-key, mixed> $row */
            static fn(array $row): RemoteList => new RemoteList(Json::string($row, 'id'), Json::string($row, 'name')),
            Json::rows(
                $this->read('lists', ['count' => self::COUNT, 'fields' => 'lists.id,lists.name'])['lists'] ?? null,
            ),
        );
    }

    #[Override]
    public function subscribe(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => $this->tag(
                $binding,
                $person,
                Outcomes::of($this->api->send(
                    'PUT',
                    self::member($binding, $person),
                    Member::subscribe($person),
                )),
            ),
            $people,
        );
    }

    #[Override]
    public function unsubscribe(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => Outcomes::gone($this->api->send(
                'PATCH',
                self::member($binding, $person),
                ['status' => 'unsubscribed'],
            )),
            $people,
        );
    }

    /**
     * Updates merge fields only, so the status is left as it is; someone not in the audience
     * isn't added.
     */
    #[Override]
    public function update(Binding $binding, array $people): array
    {
        return array_map(
            /** @throws ConnectionRefused */
            fn(Person $person): Outcome => Outcomes::of($this->api->send(
                'PATCH',
                self::member($binding, $person),
                Member::fields($person),
            )),
            $people,
        );
    }

    #[Override]
    public function verifyConnection(): Account
    {
        $account = $this->read('', ['fields' => 'account_id,account_name']);

        return new Account(Json::string($account, 'account_id'), Json::string($account, 'account_name'));
    }

    /**
     * The body of a successful read, or why it can't be had.
     *
     * @param array<string, string> $query
     *
     * @return array<array-key, mixed>
     *
     * @throws ConnectionRefused
     */
    private function read(string $path, array $query): array
    {
        $reply = $this->api->send('GET', $path, query: $query);
        if (! $reply->ok()) {
            throw ConnectionRefused::because($reply->message());
        }

        return Json::array($reply->body);
    }

    /**
     * Adds the binding's tags to someone who joined. Tags are added, never taken away, so a
     * failed tagging is safe to send again with the rest.
     *
     * @throws ConnectionRefused
     */
    private function tag(Binding $binding, Person $person, Outcome $joined): Outcome
    {
        if (OutcomeKind::Done !== $joined->kind || [] === $binding->tags) {
            return $joined;
        }

        $tagged = Outcomes::of($this->api->send('POST', self::member($binding, $person) . '/tags', [
            'tags' => array_map(self::activeTag(...), $binding->tags),
        ]));

        return OutcomeKind::Done === $tagged->kind ? $joined : $tagged;
    }
}
