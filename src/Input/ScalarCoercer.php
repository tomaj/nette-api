<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Strict scalar, enum and RFC 3339 conversion. Invalid client data throws ConversionFailed.
 */
final class ScalarCoercer
{
    private const string DATETIME = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

    public static function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value)) {
            throw new ConversionFailed('Value must be an integer');
        }
        if (ltrim($value, '+-') === '') {
            throw new ConversionFailed('Value must be an integer');
        }

        $int = (int) $value;
        if ((string) $int !== self::canonicalInteger($value)) {
            throw new ConversionFailed('Value must be an integer');
        }

        return $int;
    }

    public static function toFloat(mixed $value): float
    {
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new ConversionFailed('Value must be a number');
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $value) !== 1) {
            throw new ConversionFailed('Value must be a number');
        }

        $float = (float) $value;
        if (!is_finite($float)) {
            throw new ConversionFailed('Value must be a number');
        }

        return $float;
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1) {
            return $value === 1;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === '1' || $lower === 'true') {
                return true;
            }
            if ($lower === '0' || $lower === 'false') {
                return false;
            }
        }

        throw new ConversionFailed('Value must be a boolean');
    }

    public static function toString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ConversionFailed('Value must be a string');
        }

        return $value;
    }

    public static function toDateTime(mixed $value): \DateTimeImmutable
    {
        $message = 'DateTime must be a valid RFC 3339 date and time';
        if (!is_string($value) || preg_match(self::DATETIME, $value, $match) !== 1) {
            throw new ConversionFailed($message);
        }

        $fraction = $match[2];
        $offset = $match[3] === 'Z' ? '+00:00' : $match[3];
        if ($fraction === '') {
            $formatted = sprintf('%s%s', $match[1], $offset);
            $format = 'Y-m-d\TH:i:sP';
        } else {
            $digits = substr(sprintf('%s000000', substr($fraction, 1)), 0, 6);
            $formatted = sprintf('%s.%s%s', $match[1], $digits, $offset);
            $format = 'Y-m-d\TH:i:s.uP';
        }

        $date = \DateTimeImmutable::createFromFormat(sprintf('!%s', $format), $formatted);
        if (!$date instanceof \DateTimeImmutable || $date->format($format) !== $formatted) {
            throw new ConversionFailed($message);
        }

        return $date;
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    public static function toEnum(mixed $value, string $enum): \BackedEnum
    {
        $backing = new \ReflectionEnum($enum)->getBackingType();
        $intBacked = $backing instanceof \ReflectionNamedType && $backing->getName() === 'int';
        try {
            $coerced = $intBacked ? self::toInt($value) : self::toString($value);
        } catch (ConversionFailed) {
            throw new ConversionFailed(self::enumMessage($enum));
        }

        $case = $enum::tryFrom($coerced);
        if (!$case instanceof \BackedEnum) {
            throw new ConversionFailed(self::enumMessage($enum));
        }

        return $case;
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @return list<int|string>
     */
    public static function enumValues(string $enum): array
    {
        $values = [];
        foreach ($enum::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    public static function enumMessage(string $enum): string
    {
        return 'Value must be one of: ' . implode(', ', self::enumValues($enum));
    }

    private static function canonicalInteger(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $digits = ltrim(ltrim($value, '+-'), '0');
        if ($digits === '') {
            $digits = '0';
        }
        if ($negative && $digits !== '0') {
            return '-' . $digits;
        }

        return $digits;
    }
}
