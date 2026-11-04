<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Sync;

use Contenir\Extension\Mailchimp\Sync\Secrets;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[Group('unit')]
final class SecretsTest extends TestCase
{
    #[Test]
    public function asksFor32RandomBytes(): void
    {
        $asked = [];
        (new Secrets(static function (int $bytes) use (&$asked): string {
            $asked[] = $bytes;

            return str_repeat("\x00", $bytes);
        }))->make();

        static::assertSame([32], $asked);
    }

    #[Test]
    public function makesADifferentSecretEachTime(): void
    {
        $secrets = new Secrets();

        static::assertNotSame($secrets->make(), $secrets->make());
    }

    #[Test]
    public function makesASecretOf64HexDigits(): void
    {
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', (new Secrets())->make());
    }

    #[Test]
    public function writesTheBytesInHex(): void
    {
        static::assertSame('ffff', (new Secrets(static fn(int $bytes): string => "\xff\xff"))->make());
    }
}
