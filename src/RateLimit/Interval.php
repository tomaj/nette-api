<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * Turns an interval like 60, '30 seconds', '1 minute', '2 hours' or '1 day' into seconds
 */
final class Interval
{
    /** the longest interval, 366 days; it also keeps the arithmetic of the algorithms far from an integer overflow */
    public const int MAX = 31_622_400;

    private const array UNITS = [
        's' => 1, 'sec' => 1, 'second' => 1, 'seconds' => 1,
        'm' => 60, 'min' => 60, 'minute' => 60, 'minutes' => 60,
        'h' => 3600, 'hour' => 3600, 'hours' => 3600,
        'd' => 86400, 'day' => 86400, 'days' => 86400,
    ];

    /**
     * @return int<1, max>
     * @throws \InvalidArgumentException when the interval cannot be read
     */
    public static function toSeconds(int|string $interval, string $where = 'the rate limit interval'): int
    {
        if (is_int($interval) || preg_match('~^\d+$~D', $interval) === 1) {
            $seconds = (int) $interval;
        } elseif (preg_match('~^(\d+)\s*([a-z]+)$~i', trim($interval), $match) === 1 && array_key_exists(strtolower($match[2]), self::UNITS)) {
            [, $number, $unit] = $match;
            $seconds = intval($number) * self::UNITS[strtolower($unit)];
        } else {
            throw new \InvalidArgumentException(sprintf(
                "%s: '%s' is not an interval. Use a number of seconds (60) or a number with a unit: '30 seconds', '1 minute', '2 hours', '1 day' (units s, sec, second(s), m, min, minute(s), h, hour(s), d, day(s)).",
                $where,
                $interval,
            ));
        }

        if ($seconds > self::MAX) {
            throw new \InvalidArgumentException(sprintf("%s: '%s' is longer than 366 days. Use at most '366 days', a longer window is not supported by the storages.", $where, $interval));
        }
        if ($seconds < 1) {
            throw new \InvalidArgumentException(sprintf("%s: '%s' is shorter than 1 second. Use at least 1 second, for example '1 minute'.", $where, $interval));
        }

        return $seconds;
    }
}
