<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

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
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $match) === 1) {
                $names[] = $match[1];
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
        return array_all(explode('/', $value), fn($segment): bool => !(preg_match('/^(?:[A-Za-z0-9_.-]+|\{[A-Za-z_][A-Za-z0-9_]*\})$/', $segment) !== 1));
    }

    /**
     * Captured path parameters when $path matches $template, otherwise null.
     * Both values are endpoint URLs such as v1/users/{id}/detail and v1/users/42/detail.
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
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $match) === 1) {
                if ($actual[$index] === '') {
                    return null;
                }
                $captured[$match[1]] = $actual[$index];
                continue;
            }
            if ($segment !== $actual[$index]) {
                return null;
            }
        }

        return $captured;
    }

    /**
     * Request path reduced to the endpoint URL (v1/users/42), or null when the version is not in the path.
     */
    public static function normalize(string $requestPath, string $version): ?string
    {
        $path = parse_url($requestPath, PHP_URL_PATH);
        $path = rawurldecode(is_string($path) ? $path : $requestPath);
        $marker = sprintf('/v%s/', $version);
        $position = strpos($path, $marker);
        if ($position === false) {
            $trimmed = trim($path, '/');
            $prefix = sprintf('v%s', $version);
            if ($trimmed === $prefix || str_starts_with($trimmed, sprintf('%s/', $prefix))) {
                return $trimmed;
            }

            return null;
        }

        return trim(substr($path, $position), '/');
    }

    /**
     * @param list<string> $declared external names of the path parameters
     */
    public static function assert(string $url, array $declared, string $where): void
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
            if (str_contains($segment, '{') && preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', $segment) !== 1) {
                throw new \LogicException(sprintf(
                    "%s: '%s' is not a path parameter in the endpoint URL '%s'. Write a name in braces, for example {id}, as its own URL segment.",
                    $where,
                    $segment,
                    trim($url, '/'),
                ));
            }
        }

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
            $leftPlaceholder = preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', $segment) === 1;
            $rightPlaceholder = preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', $other) === 1;
            if (!$leftPlaceholder && !$rightPlaceholder && $segment !== $other) {
                return false;
            }
        }

        return true;
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
