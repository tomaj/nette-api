<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Interval;

final class IntervalTest extends TestCase
{
    /**
     * @return iterable<array{int|string, int}>
     */
    public static function intervals(): iterable
    {
        yield [1, 1];
        yield ['60', 60];
        yield ['30 seconds', 30];
        yield ['1s', 1];
        yield ['5 sec', 5];
        yield ['1 second', 1];
        yield [' 2 m ', 120];
        yield ['10 min', 600];
        yield ['1 minute', 60];
        yield ['3 minutes', 180];
        yield ['1h', 3600];
        yield ['1 hour', 3600];
        yield ['2 Hours', 7200];
        yield ['1 d', 86400];
        yield ['1 day', 86400];
        yield ['7 days', 604800];
        yield ['366 days', 31_622_400];
    }

    #[DataProvider('intervals')]
    public function testToSeconds(int|string $interval, int $seconds): void
    {
        $this->assertSame($seconds, Interval::toSeconds($interval));
    }

    /**
     * @return iterable<array{int|string, string}>
     */
    public static function invalid(): iterable
    {
        $units = "Use a number of seconds (60) or a number with a unit: '30 seconds', '1 minute', '2 hours', '1 day' (units s, sec, second(s), m, min, minute(s), h, hour(s), d, day(s)).";
        yield ['1 week', "the rate limit interval: '1 week' is not an interval. " . $units];
        yield ['minute', "the rate limit interval: 'minute' is not an interval. " . $units];
        yield ['1.5 minutes', "the rate limit interval: '1.5 minutes' is not an interval. " . $units];
        yield ['x1 minute', "the rate limit interval: 'x1 minute' is not an interval. " . $units];
        yield ['1 minutex', "the rate limit interval: '1 minutex' is not an interval. " . $units];
        yield ['1 minute 5', "the rate limit interval: '1 minute 5' is not an interval. " . $units];
        yield ["60\n", "the rate limit interval: '60\n' is not an interval. " . $units];
        yield [' 60', "the rate limit interval: ' 60' is not an interval. " . $units];
        yield ['367 days', "the rate limit interval: '367 days' is longer than 366 days. Use at most '366 days', a longer window is not supported by the storages."];
        yield [31_622_401, "the rate limit interval: '31622401' is longer than 366 days."];
        yield ['99999999999999999999 days', "the rate limit interval: '99999999999999999999 days' is longer than 366 days."];
        yield ['99999999999999999999', "the rate limit interval: '99999999999999999999' is longer than 366 days."];
        yield [-5, "the rate limit interval: '-5' is shorter than 1 second. Use at least 1 second, for example '1 minute'."];
        yield [0, "the rate limit interval: '0' is shorter than 1 second. Use at least 1 second, for example '1 minute'."];
        yield ['0 minutes', "the rate limit interval: '0 minutes' is shorter than 1 second. Use at least 1 second, for example '1 minute'."];
    }

    #[DataProvider('invalid')]
    public function testInvalid(int|string $interval, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        Interval::toSeconds($interval);
    }

    public function testWhere(): void
    {
        $this->expectExceptionMessage("rateLimits.perIp.interval: 'soon' is not an interval.");
        Interval::toSeconds('soon', 'rateLimits.perIp.interval');
    }
}
