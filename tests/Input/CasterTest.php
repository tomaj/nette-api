<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\CastException;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Input\UnixTimestamp;

final class CasterTest extends TestCase
{
    public function testDateFormat(): void
    {
        $caster = new DateFormat('d.m.Y');
        $date = $caster->cast('24.12.2026');
        $this->assertSame('2026-12-24 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame(['type' => 'string', 'format' => 'date', 'example' => '24.12.2026'], $caster->openApiSchema());

        foreach (['31.02.2026', '24.12.2026 ', '2026-12-24', 1, ''] as $value) {
            try {
                $caster->cast($value);
                $this->fail('accepted ' . var_export($value, true));
            } catch (CastException $exception) {
                $this->assertSame('Date must match d.m.Y, for example 24.12.2026', $exception->getMessage());
            }
        }
    }

    public function testDateFormatRejectsAnEmptyFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DateFormat('');
    }

    public function testUnixTimestamp(): void
    {
        $caster = new UnixTimestamp();
        $this->assertSame('2026-12-24T00:00:00+00:00', $caster->cast('1798070400')->format('c'));
        $this->assertSame('2026-12-24T00:00:00+00:00', $caster->cast(1798070400)->format('c'));
        $this->assertSame(['type' => 'string', 'example' => '1798185600'], $caster->openApiSchema());

        $this->expectException(CastException::class);
        $caster->cast('1798070400abc');
    }

    public function testSplitKeepsEmptyPieces(): void
    {
        $caster = new Split(',');
        $this->assertSame(['red', 'green', 'blue'], $caster->cast('red,green,blue'));
        $this->assertSame([''], $caster->cast(''));
        $this->assertSame(['a', '', 'b'], $caster->cast('a,,b'));
        $this->assertSame(['type' => 'string', 'example' => 'red,green,blue'], $caster->openApiSchema());
        $this->assertSame('red|green|blue', new Split('|')->openApiSchema()['example']);

        $this->expectException(CastException::class);
        $caster->cast(['red']);
    }

    public function testSplitRejectsAnEmptySeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Split('');
    }
}
