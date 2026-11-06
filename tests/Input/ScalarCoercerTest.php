<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\ConversionFailed;
use Tomaj\NetteApi\Input\ScalarCoercer;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;

final class ScalarCoercerTest extends TestCase
{
    public function testIntegers(): void
    {
        $this->assertSame(2, ScalarCoercer::toInt(2));
        $this->assertSame(2, ScalarCoercer::toInt('2'));
        $this->assertSame(2, ScalarCoercer::toInt('+2'));
        $this->assertSame(2, ScalarCoercer::toInt('02'));
        $this->assertSame(-3, ScalarCoercer::toInt('-3'));
        $this->assertSame(0, ScalarCoercer::toInt('-0'));
        $this->assertSame(PHP_INT_MAX, ScalarCoercer::toInt((string) PHP_INT_MAX));

        foreach (['1a', 'x1', '1.0', ' 1', '', '1 ', '+', '-', true, 1.5, null, []] as $value) {
            try {
                ScalarCoercer::toInt($value);
                $this->fail('accepted ' . var_export($value, true));
            } catch (ConversionFailed $exception) {
                $this->assertSame('Value must be an integer', $exception->getMessage());
            }
        }
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toInt((string) PHP_INT_MAX . '0');
    }

    public function testFloats(): void
    {
        $this->assertEqualsWithDelta(2.0, ScalarCoercer::toFloat(2), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(1.5, ScalarCoercer::toFloat(1.5), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(1.5, ScalarCoercer::toFloat('1.5'), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.5, ScalarCoercer::toFloat('.5'), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(100.0, ScalarCoercer::toFloat('1e2'), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.015, ScalarCoercer::toFloat('+1.5e-2'), PHP_FLOAT_EPSILON);

        foreach (['1a', 'nan', 'INF', '-INF', '', ' 1', true] as $value) {
            try {
                ScalarCoercer::toFloat($value);
                $this->fail('accepted ' . var_export($value, true));
            } catch (ConversionFailed $exception) {
                $this->assertSame('Value must be a number', $exception->getMessage());
            }
        }
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toFloat(NAN);
    }

    public function testFloatRejectsInfinity(): void
    {
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toFloat(INF);
    }

    public function testBooleans(): void
    {
        $this->assertTrue(ScalarCoercer::toBool(true));
        $this->assertFalse(ScalarCoercer::toBool(false));
        $this->assertTrue(ScalarCoercer::toBool(1));
        $this->assertFalse(ScalarCoercer::toBool(0));
        $this->assertTrue(ScalarCoercer::toBool('TRUE'));
        $this->assertFalse(ScalarCoercer::toBool('False'));
        $this->assertTrue(ScalarCoercer::toBool('1'));
        $this->assertFalse(ScalarCoercer::toBool('0'));

        foreach (['yes', '2', '', 2, null, []] as $value) {
            try {
                ScalarCoercer::toBool($value);
                $this->fail('accepted ' . var_export($value, true));
            } catch (ConversionFailed $exception) {
                $this->assertSame('Value must be a boolean', $exception->getMessage());
            }
        }
    }

    public function testStringsAndDateTimes(): void
    {
        $this->assertSame('ada', ScalarCoercer::toString('ada'));
        $this->expectException(ConversionFailed::class);
        ScalarCoercer::toString(1);
    }

    public function testDateTimeAcceptsRfc3339(): void
    {
        $date = ScalarCoercer::toDateTime('2026-11-06T14:30:00+01:00');
        $this->assertSame('2026-11-06T14:30:00+01:00', $date->format('Y-m-d\TH:i:sP'));

        $utc = ScalarCoercer::toDateTime('2026-11-06T13:30:00Z');
        $this->assertSame('+00:00', $utc->format('P'));

        $fraction = ScalarCoercer::toDateTime('2026-11-06T14:30:00.5+01:00');
        $this->assertSame('500000', $fraction->format('u'));
    }

    public function testDateTimeRejectsIncompleteValues(): void
    {
        foreach (['2026-11-06', '2026-11-06T14:30:00', '2026-02-31T14:30:00Z', '2026-11-06T14:30:00+01', 1, ''] as $value) {
            try {
                ScalarCoercer::toDateTime($value);
                $this->fail('accepted ' . var_export($value, true));
            } catch (ConversionFailed $exception) {
                $this->assertSame('DateTime must be a valid RFC 3339 date and time', $exception->getMessage());
            }
        }
    }

    public function testEnums(): void
    {
        $this->assertSame(UserStatus::Active, ScalarCoercer::toEnum('active', UserStatus::class));
        $this->assertSame(Priority::High, ScalarCoercer::toEnum('2', Priority::class));
        $this->assertSame(Priority::High, ScalarCoercer::toEnum(2, Priority::class));

        try {
            ScalarCoercer::toEnum('nope', UserStatus::class);
            $this->fail('accepted unknown enum');
        } catch (ConversionFailed $exception) {
            $this->assertSame('Value must be one of: active, disabled', $exception->getMessage());
        }
        try {
            ScalarCoercer::toEnum('1a', Priority::class);
            $this->fail('accepted bad int enum');
        } catch (ConversionFailed $exception) {
            $this->assertSame('Value must be one of: 1, 2', $exception->getMessage());
        }
    }
}
