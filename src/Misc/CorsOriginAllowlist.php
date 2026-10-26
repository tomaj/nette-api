<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use InvalidArgumentException;

/**
 * @internal used by ApiPresenter::setCorsAllowedOrigins(), may change in 4.0
 *
 * Explicit list of origins allowed to call the API from a browser.
 *
 * The request `Origin` header is compared with the list (scheme, host and port, case-insensitive).
 * Default ports (http :80, https :443) are dropped from the configured origins, as browsers do.
 * Only a matching origin is reflected in `Access-Control-Allow-Origin`; any other origin gets no CORS header.
 */
final class CorsOriginAllowlist
{
    /** @var string[] normalized (lowercase) origins */
    private array $origins = [];

    /**
     * @param string[] $origins e.g. ['https://app.example.com', 'http://localhost:3000']
     * @param bool $allowCredentials send `Access-Control-Allow-Credentials: true` for allowed origins
     */
    public function __construct(array $origins, private bool $allowCredentials = false)
    {
        if ($origins === []) {
            throw new InvalidArgumentException('CORS allowlist must contain at least one origin');
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

    private const DEFAULT_PORTS = ['http' => '80', 'https' => '443'];

    private const ORIGIN_PATTERN = '~^([a-z][a-z0-9+.-]*)://([a-z0-9.-]+|\\[[0-9a-f:.]+\\])(?::([1-9][0-9]{0,4}))?$~iD';

    /**
     * Normalize to the form browsers send in the Origin header (lowercase, no default port).
     * Hosts must be ASCII (use punycode for IDN), trailing whitespace or slash is rejected.
     */
    private static function normalize(string $origin): string
    {
        if (preg_match(self::ORIGIN_PATTERN, $origin, $match) !== 1 || (isset($match[3]) && $match[3] > 65535)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid CORS origin "%s", expected scheme://host[:port] without path, e.g. "https://app.example.com"',
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
