<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Webhook;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\Field\FieldMap;
use Contenir\Audience\Field\FieldRule;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\People;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Factory\Settings;
use Contenir\Extension\Mailchimp\Webhook\SignedBinding;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SignedBindingTest extends TestCase
{
    /**
     * @return array<string, array{RegisteredWebhook|null}>
     */
    public static function unsignedProvider(): array
    {
        return [
            'no webhook'           => [null],
            'no signing secret'    => [new RegisteredWebhook('newsletter', 'w1', null, new DateTimeImmutable())],
            'an empty signing one' => [new RegisteredWebhook('newsletter', 'w1', '', new DateTimeImmutable())],
        ];
    }

    private static function binding(): Binding
    {
        return new Binding(
            'newsletter',
            People::LIST,
            'library.members.member',
            'work_email',
            new FieldMap([new FieldRule('FNAME', 'name')]),
            'Signing up',
            false,
            ['website'],
            OnDelete::Keep,
            ['consent_to_track' => 'Yes'],
        );
    }

    #[Test]
    public function addsTheSigningSecretAndKeepsEverythingElse(): void
    {
        static::assertEquals(
            new Binding(
                'newsletter',
                People::LIST,
                'library.members.member',
                'work_email',
                new FieldMap([new FieldRule('FNAME', 'name')]),
                'Signing up',
                false,
                ['website'],
                OnDelete::Keep,
                ['consent_to_track' => 'Yes', 'signing_secret' => Settings::SIGNING],
            ),
            SignedBinding::of(
                self::binding(),
                new RegisteredWebhook('newsletter', 'w1', Settings::SIGNING, new DateTimeImmutable()),
            ),
        );
    }

    #[DataProvider('unsignedProvider')]
    #[Test]
    public function leavesTheBindingAsItIsWithoutASigningSecret(?RegisteredWebhook $webhook): void
    {
        $binding = self::binding();

        static::assertSame($binding, SignedBinding::of($binding, $webhook));
    }
}
