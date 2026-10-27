<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Tomaj\NetteApi\Misc\TypeHelper;

final class TypeHelperTest extends TestCase
{
    public static function toStringProvider(): \Iterator
    {
        yield 'string' => ['abc', 'abc'];
        yield 'null' => [null, ''];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'list' => [['a', 1], '["a",1]'];
        yield 'unicode and slashes' => [['k' => 'á/b'], '{"k":"á/b"}'];
        yield 'invalid utf8 partial' => [["\xB1"], '[null]'];
    }

    #[DataProvider('toStringProvider')]
    public function testToString(mixed $value, string $expected): void
    {
        $this->assertSame($expected, TypeHelper::toString($value));
    }

    public function testToStringStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'str';
            }
        };
        $this->assertSame('str', TypeHelper::toString($stringable));
    }

    public function testToArray(): void
    {
        $this->assertSame(['a' => 1, 2 => 'b'], TypeHelper::toArray(['a' => 1, 2 => 'b']));
        $this->assertSame([], TypeHelper::toArray('x'));
        $this->assertSame([], TypeHelper::toArray(null));
    }

    public function testToStringList(): void
    {
        $this->assertSame(['1', 'b', ''], TypeHelper::toStringList(['x' => 1, 5 => 'b', 7 => null]));
        $this->assertSame([], TypeHelper::toStringList('abc'));
    }

    public function testToStringFloatUsesStringCast(): void
    {
        $this->assertSame('INF', TypeHelper::toString(INF));
        $this->assertSame('1.0E+100', TypeHelper::toString(1e100));
    }
}
