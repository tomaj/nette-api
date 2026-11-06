<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

/**
 * Path parameters captured for the current request. ApiDecider fills this before params are read.
 */
final class PathValues
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function reset(): void
    {
        self::$values = [];
    }

    /**
     * @param array<string, string> $values
     */
    public static function set(array $values): void
    {
        self::$values = $values;
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$values);
    }

    public static function get(string $name): ?string
    {
        return self::$values[$name] ?? null;
    }
}
