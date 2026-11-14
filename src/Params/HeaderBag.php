<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

/**
 * Case-insensitive HTTP headers from the PHP request. CLI and the built-in server expose them in $_SERVER, Apache after a rewrite as REDIRECT_HTTP_AUTHORIZATION.
 */
final class HeaderBag
{
    public static function has(string $name): bool
    {
        return array_key_exists(strtolower($name), self::all());
    }

    public static function get(string $name): ?string
    {
        $value = self::all()[strtolower($name)] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
        }
        // Apache with CGI/FastCGI hands the Authorization header over as REDIRECT_HTTP_AUTHORIZATION after a rewrite
        if (!array_key_exists('authorization', $headers) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (array_key_exists('CONTENT_TYPE', $_SERVER)) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (array_key_exists('CONTENT_LENGTH', $_SERVER)) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
