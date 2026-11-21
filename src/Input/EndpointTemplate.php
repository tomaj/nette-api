<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Nette\Utils\Strings;

/**
 * Matches an endpoint URL that contains {name} segments and checks it against declared path parameters.
 */
final class EndpointTemplate
{
    /**
     * @return list<string>
     */
    public static function placeholders(string $url): array
    {
        $names = [];
        foreach (self::segments($url) as $segment) {
            $name = self::placeholderName($segment);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * True when a package or action may contain slashes because it is a {name} template.
     */
    public static function isComposite(string $value): bool
    {
        if (!str_contains($value, '{') || !str_contains($value, '/')) {
            return false;
        }
        return array_all(
            explode('/', $value),
            static fn (string $segment): bool => self::placeholderName($segment) !== null || preg_match('/^[A-Za-z0-9_.-]+$/', $segment) === 1,
        );
    }

    /**
     * Captured path parameters when $path matches $template, otherwise null.
     * Both are paths after the version, such as users/{id}/detail and users/42/detail; $path is already decoded by the router.
     *
     * @return array<string, string>|null
     */
    public static function match(string $template, string $path): ?array
    {
        $expected = self::segments($template);
        $actual = self::segments($path);
        if (count($expected) !== count($actual)) {
            return null;
        }

        $captured = [];
        foreach ($expected as $index => $segment) {
            $value = $actual[$index];
            $name = self::placeholderName($segment);
            if ($name !== null) {
                if (in_array($value, ['', '.', '..'], true)) {
                    // the same values fill() refuses: a dot segment, decoded from %2E, must not reach a handler as a path value
                    return null;
                }
                $captured[$name] = $value;
                continue;
            }
            if ($segment !== $value) {
                return null;
            }
        }

        return $captured;
    }

    /**
     * Fills the {name} segments of $template with URL-encoded values from $values. A segment without a scalar value
     * stays literal, which is the OpenAPI path template. Returns the path and the values that were not used.
     *
     * @param array<mixed> $values
     * @return array{0: string, 1: array<mixed>}
     * @throws \InvalidArgumentException when a value is empty, a dot segment or contains a slash, so the URL would lead elsewhere
     */
    public static function fill(string $template, array $values): array
    {
        $path = Strings::replace($template, '/\{([A-Za-z_][A-Za-z0-9_]*)\}/', static function (array $match) use (&$values): string {
            /** @var array{string, string} $match */
            $name = $match[1];
            if (!isset($values[$name]) || !is_scalar($values[$name])) {
                return $match[0];
            }
            $value = (string) $values[$name];
            if (in_array($value, ['', '.', '..'], true) || str_contains($value, '/')) {
                // an empty segment is never matched, a dot segment is resolved away by clients and a slash splits the segment
                throw new \InvalidArgumentException(sprintf("Path value '%s' cannot be '%s'. Use a non-empty value without a slash that is not '.' or '..'.", $name, $value));
            }
            unset($values[$name]);

            return rawurlencode($value);
        });

        return [$path, $values];
    }

    /**
     * @param list<string> $declared external names of the path parameters
     */
    public static function assert(string $url, array $declared, string $where): void
    {
        self::assertSyntax($url, $where);
        $placeholders = self::placeholders($url);
        $missing = array_diff($placeholders, $declared);
        $undeclared = array_diff($declared, $placeholders);
        if ($missing === [] && $undeclared === []) {
            return;
        }

        throw new \LogicException(sprintf(
            "%s: the endpoint URL '%s' and its path parameters do not match. The URL has {%s} and the handler declares [%s]. Every {name} needs a #[Path] property or a PathInputParam with that name, and every path parameter must appear in the URL, for example #[Get(1, 'users', '{id}/detail')] with #[Path] public int \$id.",
            $where,
            trim($url, '/'),
            implode(', ', $placeholders),
            implode(', ', $declared),
        ));
    }

    /**
     * Each {name} is a whole segment and appears once.
     */
    public static function assertSyntax(string $url, string $where): void
    {
        $placeholders = self::placeholders($url);
        $duplicatePlaceholders = array_diff_assoc($placeholders, array_unique($placeholders));
        if ($duplicatePlaceholders !== []) {
            throw new \LogicException(sprintf(
                "%s: the endpoint URL '%s' repeats {%s}. Use each path parameter once, for example users/{id}/detail.",
                $where,
                trim($url, '/'),
                implode(', ', array_unique($duplicatePlaceholders)),
            ));
        }

        foreach (self::segments($url) as $segment) {
            if (str_contains($segment, '{') && self::placeholderName($segment) === null) {
                throw new \LogicException(sprintf(
                    "%s: '%s' is not a path parameter in the endpoint URL '%s'. Write a name in braces, for example {id}, as its own URL segment.",
                    $where,
                    $segment,
                    trim($url, '/'),
                ));
            }
        }
    }

    /**
     * Two templates of the same method that can match one request.
     *
     * @param array<string, list<string>> $urlsByMethod method => endpoint URLs
     */
    public static function assertDisjoint(array $urlsByMethod): void
    {
        foreach ($urlsByMethod as $method => $urls) {
            $templates = $urls;
            $count = count($templates);
            for ($left = 0; $left < $count; $left++) {
                $leftUrl = $templates[$left];
                for ($right = $left + 1; $right < $count; $right++) {
                    if (self::overlaps($leftUrl, $templates[$right])) {
                        throw new \LogicException(sprintf(
                            "%s endpoints '%s' and '%s' can match the same request. Make a static segment differ, or give them a different number of segments, so only one template matches.",
                            $method,
                            trim($templates[$left], '/'),
                            trim($templates[$right], '/'),
                        ));
                    }
                }
            }
        }
    }

    public static function overlaps(string $left, string $right): bool
    {
        if (!str_contains($left, '{') || !str_contains($right, '{')) {
            return false;
        }

        $leftSegments = self::segments($left);
        $rightSegments = self::segments($right);
        if (count($leftSegments) !== count($rightSegments)) {
            return false;
        }

        foreach ($leftSegments as $index => $segment) {
            $other = $rightSegments[$index];
            if (self::placeholderName($segment) === null && self::placeholderName($other) === null && $segment !== $other) {
                return false;
            }
        }

        return true;
    }

    /**
     * The name of a {name} segment, null for a static segment.
     */
    private static function placeholderName(string $segment): ?string
    {
        return preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $match) === 1 ? $match[1] : null;
    }

    /**
     * @return list<string>
     */
    private static function segments(string $url): array
    {
        $url = trim($url, '/');
        if ($url === '') {
            return [];
        }

        return explode('/', $url);
    }
}
