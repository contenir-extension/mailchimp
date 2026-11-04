<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Webhook;

use Contenir\Audience\Service\InvalidWebhook;
use Contenir\Extension\Mailchimp\Webhook\Signature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hash_hmac;

#[Group('unit')]
final class SignatureTest extends TestCase
{
    private const string BODY = 'type=unsubscribe&data%5Blist_id%5D=f1e2d3c4b5';

    private const int NOW = 1_793_790_000;

    private const string SIGNED_WITH = 'signed-in-tests';

    /**
     * @return array<string, array{int}>
     */
    public static function inTimeProvider(): array
    {
        return [
            'just now'              => [self::NOW],
            'five minutes ago'      => [self::NOW - 300],
            'five minutes from now' => [self::NOW + 300],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function outOfTimeProvider(): array
    {
        return [
            'a second too old'       => [self::NOW - 301],
            'a second too far ahead' => [self::NOW + 301],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsignedProvider(): array
    {
        return [
            'no header'        => [''],
            'no time'          => ['v1=abc'],
            'a time not whole' => ['t=12.5,v1=abc'],
            'no signature'     => ['t=' . self::NOW],
        ];
    }

    private static function header(int $time, string $signedWith = self::SIGNED_WITH): string
    {
        return "t={$time},v1=" . hash_hmac('sha256', "{$time}." . self::BODY, $signedWith);
    }

    #[Test]
    #[DataProvider('inTimeProvider')]
    public function acceptsACallSignedWithTheSecretLately(int $time): void
    {
        $this->expectNotToPerformAssertions();

        Signature::verify(self::header($time), self::BODY, self::SIGNED_WITH, self::NOW);
    }

    #[Test]
    #[DataProvider('outOfTimeProvider')]
    public function refusesACallSignedMoreThanFiveMinutesOut(int $time): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: its signature is more than five minutes out');

        Signature::verify(self::header($time), self::BODY, self::SIGNED_WITH, self::NOW);
    }

    #[Test]
    public function refusesACallSignedWithAnotherSecret(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: its signature doesn\'t match');

        Signature::verify(self::header(self::NOW, 'another-secret'), self::BODY, self::SIGNED_WITH, self::NOW);
    }

    #[Test]
    #[DataProvider('unsignedProvider')]
    public function refusesACallThatIsNotSigned(string $header): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: it isn\'t signed');

        Signature::verify($header, self::BODY, self::SIGNED_WITH, self::NOW);
    }

    #[Test]
    public function refusesACallWhoseBodyWasChanged(): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessage('The webhook call was refused: its signature doesn\'t match');

        Signature::verify(self::header(self::NOW), self::BODY . '&x=1', self::SIGNED_WITH, self::NOW);
    }
}
