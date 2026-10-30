<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use InvalidArgumentException;

/**
 * @internal used by ApiPresenter::setCorsAllowedOrigins() and NetteApiExtension, may change in 4.x
 *
 * Explicit list of origins allowed to call the API from a browser.
 *
 * The request `Origin` header is compared with the list (scheme, host and port, case-insensitive).
 * Default ports (http :80, https :443) are dropped from the configured origins, as browsers do.
 * Only a matching origin is reflected in `Access-Control-Allow-Origin`; any other origin gets no CORS header.
 */
final class CorsOriginAllowlist implements CorsOriginPolicy
{
    /** @var string[] normalized (lowercase) origins */
    private array $origins = [];

    /**
     * @param string[] $origins e.g. ['https://app.example.com', 'http://localhost:3000']
     * @param bool $allowCredentials send `Access-Control-Allow-Credentials: true` for allowed origins
     */
    public function __construct(array $origins, private readonly bool $allowCredentials = false)
    {
        if ($origins === []) {
            throw new InvalidArgumentException("setCorsAllowedOrigins() got an empty list, so no origin could call the API. Pass the origins, for example setCorsAllowedOrigins(['https://app.example.com']), or do not call it.");
        }

        foreach ($origins as $origin) {
            $this->origins[] = self::normalize($origin);
        }
    }

    /**
     * CORS response headers for a request with the given `Origin` header value
     *
     * @return array<string, string>
     */
    #[\Override]
    public function headers(?string $requestOrigin): array
    {
        if ($requestOrigin === null || !in_array(strtolower($requestOrigin), $this->origins, true)) {
            return ['Vary' => 'Origin'];
        }

        $headers = ['Vary' => 'Origin', 'Access-Control-Allow-Origin' => $requestOrigin];
        if ($this->allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }

    private const array DEFAULT_PORTS = ['http' => '80', 'https' => '443'];

    private const string ORIGIN_PATTERN = '~^([a-z][a-z0-9+.-]*)://([a-z0-9.-]+|\\[[0-9a-f:.]+\\])(?::([1-9][0-9]{0,4}))?$~iD';

    /**
     * Whether the value is an origin in the form scheme://host[:port], as browsers send it in the Origin header
     */
    public static function isOrigin(string $value): bool
    {
        return self::parse($value) !== null;
    }

    /**
     * @return array{string, string, string, 3?: string}|null scheme, host and port of an origin
     */
    private static function parse(string $value): ?array
    {
        if (preg_match(self::ORIGIN_PATTERN, $value, $match) !== 1 || (isset($match[3]) && $match[3] > 65535)) {
            return null;
        }

        return $match;
    }

    /**
     * Normalize to the form browsers send in the Origin header (lowercase, no default port).
     * Hosts must be ASCII (use punycode for IDN), trailing whitespace or slash is rejected.
     */
    private static function normalize(string $origin): string
    {
        $match = self::parse($origin);
        if ($match === null) {
            throw new InvalidArgumentException(sprintf(
                "setCorsAllowedOrigins(): '%s' is not an origin, it would never match the Origin header a browser sends. Write the scheme and host (and port) without a path or trailing slash, for example 'https://app.example.com' or 'http://localhost:3000'.",
                $origin,
            ));
        }

        $scheme = strtolower($match[1]);
        $normalized = $scheme . '://' . strtolower($match[2]);
        $port = $match[3] ?? '';
        if ($port !== '' && $port !== (self::DEFAULT_PORTS[$scheme] ?? null)) {
            $normalized .= ':' . $port;
        }

        return $normalized;
    }
}
