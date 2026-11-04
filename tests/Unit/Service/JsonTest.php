<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Service;

use Contenir\Extension\Mailchimp\Service\Json;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class JsonTest extends TestCase
{
    #[Test]
    public function readsAJsonObject(): void
    {
        static::assertSame(['type' => 'cleaned'], Json::document('{"type":"cleaned"}'));
    }

    #[Test]
    public function readsAnArrayOrAnEmptyOneInItsPlace(): void
    {
        static::assertSame([['id' => 'a'], []], [Json::array(['id' => 'a']), Json::array('a')]);
    }

    #[Test]
    public function readsAnEmptyStringFromAnotherType(): void
    {
        static::assertSame(['', '', ''], [
            Json::string(['id' => 1], 'id'),
            Json::string([], 'id'),
            Json::string(null, 'id'),
        ]);
    }

    #[Test]
    public function readsAString(): void
    {
        static::assertSame('abc', Json::string(['id' => 'abc'], 'id'));
    }

    #[Test]
    public function readsNoRowsFromSomethingOtherThanAnArray(): void
    {
        static::assertSame([], Json::rows('rows'));
    }

    #[Test]
    public function readsNothingFromJsonThatIsNotAnObjectOrArray(): void
    {
        static::assertNull(Json::document('"cleaned"'));
    }

    #[Test]
    public function readsNothingFromTextThatIsNotJson(): void
    {
        static::assertNull(Json::document('type=cleaned'));
    }

    #[Test]
    public function readsOnlyTheObjectsInAnArray(): void
    {
        static::assertSame([['a' => 1], ['b' => 2]], Json::rows([['a' => 1], 'x', 'k' => ['b' => 2]]));
    }

    #[Test]
    public function readsTrueOnlyFromTrue(): void
    {
        static::assertSame(
            [true, false, false, false],
            [
                Json::bool(['required' => true], 'required'),
                Json::bool(['required' => 1], 'required'),
                Json::bool([], 'required'),
                Json::bool(null, 'required'),
            ],
        );
    }
}
