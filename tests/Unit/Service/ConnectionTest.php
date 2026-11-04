<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Extension\Mailchimp\Service\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_encode;

#[Group('unit')]
final class ConnectionTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function keyWithoutCentreProvider(): array
    {
        return [
            'no data centre'       => ['0123456789abcdef'],
            'an empty data centre' => ['0123456789abcdef-'],
            'letters only'         => ['0123456789abcdef-us'],
            'digits only'          => ['0123456789abcdef-21'],
            'something after it'   => ['0123456789abcdef-us21x'],
            'something before it'  => ['0123456789abcdef-Xus21'],
        ];
    }

    #[Test]
    public function reachesTheDataCentreTheKeyEndsWith(): void
    {
        static::assertSame('https://us21.api.mailchimp.com/3.0/', (new Connection('0123-456-us21'))->base);
    }

    #[Test]
    #[DataProvider('keyWithoutCentreProvider')]
    public function refusesAKeyWithoutItsDataCentre(string $key): void
    {
        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('a Mailchimp API key ends with its data centre, such as -us21');

        new Connection($key);
    }

    #[Test]
    public function signsInWithTheKeyAsTheBasicPassword(): void
    {
        static::assertSame(
            'Basic ' . base64_encode('contenir:0123-us21'),
            (new Connection('0123-us21'))->authorization(),
        );
    }
}
