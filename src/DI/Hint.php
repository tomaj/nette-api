<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

/**
 * Builds the "did you mean" part of configuration error messages.
 *
 * @internal used by NetteApiExtension at container compile time
 */
final class Hint
{
    /**
     * The closest of $options to $value (case-insensitive), or null when none is close enough to be a typo.
     *
     * @param iterable<string> $options
     */
    private static function closest(string $value, iterable $options): ?string
    {
        $best = null;
        $bestDistance = max(2, intdiv(strlen($value), 3)) + 1;
        foreach ($options as $option) {
            $distance = levenshtein(strtolower($value), strtolower($option));
            if ($distance < $bestDistance) {
                $best = $option;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * " Did you mean 'x'?" or an empty string
     *
     * @param iterable<string> $options
     */
    public static function didYouMean(string $value, iterable $options): string
    {
        $closest = self::closest($value, $options);
        return $closest === null ? '' : sprintf(" Did you mean '%s'?", $closest);
    }

    /**
     * 'a', 'b', 'c'
     *
     * @param array<string> $values
     */
    public static function quoted(array $values): string
    {
        return "'" . implode("', '", $values) . "'";
    }
}
