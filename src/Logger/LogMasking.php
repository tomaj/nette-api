<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Logger;

use Tomaj\NetteApi\DI\Hint;

/**
 * Which request headers and query parameters ApiPresenter masks before it calls ApiLoggerInterface, and how.
 *
 * Names compare case-insensitively. A pattern is a header name with * for any characters, for example *token* or x-auth-*.
 * Emptying the header (or query parameter) list with the replace-all setter turns that masking off, including the patterns
 * and the names read from the registered authorizations; adding an entry turns it on again.
 */
final class LogMasking
{
    public const array DEFAULT_HEADERS = ['Authorization', 'Proxy-Authorization', 'Cookie', 'Set-Cookie', 'X-Api-Key'];

    public const array DEFAULT_HEADER_PATTERNS = ['*token*', '*secret*', '*key*', 'x-auth-*'];

    public const array DEFAULT_QUERY_PARAMS = ['api_key', 'apikey', 'key', 'token', 'access_token', 'refresh_token', 'id_token', 'auth', 'password', 'secret', 'signature', 'sig', 'code', 'client_secret'];

    /** @var array<string, string> lowercase name => name as given */
    private array $headers = [];

    /** @var array<string, string> lowercase pattern => pattern as given */
    private array $headerPatterns = [];

    /** @var array<string, string> lowercase name => name as given */
    private array $queryParams = [];

    private bool $headersOn = true;

    private bool $queryParamsOn = true;

    private LogValueMaskerInterface $masker;

    public function __construct()
    {
        $this->headers = self::keyed(self::DEFAULT_HEADERS);
        $this->headerPatterns = self::keyed(self::DEFAULT_HEADER_PATTERNS);
        $this->queryParams = self::keyed(self::DEFAULT_QUERY_PARAMS);
        $this->masker = new LogValueMasker();
    }

    /**
     * @param string[] $headers
     */
    public function setHeaders(array $headers, string $call): void
    {
        foreach ($headers as $header) {
            self::check(self::headerNameProblem($header), $call, $header, true);
        }
        $this->headers = self::keyed($headers);
        $this->headersOn = $headers !== [];
        if (!$this->headersOn) {
            $this->headerPatterns = [];
        }
    }

    public function addHeader(string $header, string $call): void
    {
        self::check(self::headerNameProblem($header), $call, $header);
        $this->headers[strtolower($header)] = $header;
        $this->headersOn = true;
    }

    public function removeHeader(string $header, string $call): void
    {
        if (!isset($this->headers[strtolower($header)])) {
            throw new \InvalidArgumentException(sprintf('%s: %s', self::call($call, $header), self::notInListProblem($header, $this->headers, 'headers', $this->headerPatterns)));
        }
        unset($this->headers[strtolower($header)]);
    }

    /**
     * @return list<string>
     */
    public function getHeaders(): array
    {
        return array_values($this->headers);
    }

    /**
     * @param string[] $patterns
     */
    public function setHeaderPatterns(array $patterns, string $call): void
    {
        foreach ($patterns as $pattern) {
            self::check(self::headerPatternProblem($pattern), $call, $pattern, true);
        }
        $this->headerPatterns = self::keyed($patterns);
        $this->headersOn = $this->headersOn || $patterns !== [];
    }

    public function addHeaderPattern(string $pattern, string $call): void
    {
        self::check(self::headerPatternProblem($pattern), $call, $pattern);
        $this->headerPatterns[strtolower($pattern)] = $pattern;
        $this->headersOn = true;
    }

    public function removeHeaderPattern(string $pattern, string $call): void
    {
        if (!isset($this->headerPatterns[strtolower($pattern)])) {
            throw new \InvalidArgumentException(sprintf('%s: %s', self::call($call, $pattern), self::notInListProblem($pattern, $this->headerPatterns, 'header patterns')));
        }
        unset($this->headerPatterns[strtolower($pattern)]);
    }

    /**
     * @return list<string>
     */
    public function getHeaderPatterns(): array
    {
        return array_values($this->headerPatterns);
    }

    /**
     * @param string[] $params
     */
    public function setQueryParams(array $params, string $call): void
    {
        foreach ($params as $param) {
            self::check(self::queryParamProblem($param), $call, $param, true);
        }
        $this->queryParams = self::keyed($params);
        $this->queryParamsOn = $params !== [];
    }

    public function addQueryParam(string $param, string $call): void
    {
        self::check(self::queryParamProblem($param), $call, $param);
        $this->queryParams[strtolower($param)] = $param;
        $this->queryParamsOn = true;
    }

    public function removeQueryParam(string $param, string $call): void
    {
        if (!isset($this->queryParams[strtolower($param)])) {
            throw new \InvalidArgumentException(sprintf('%s: %s', self::call($call, $param), self::notInListProblem($param, $this->queryParams, 'query parameters')));
        }
        unset($this->queryParams[strtolower($param)]);
    }

    /**
     * @return list<string>
     */
    public function getQueryParams(): array
    {
        return array_values($this->queryParams);
    }

    public function setMasker(LogValueMaskerInterface $masker): void
    {
        $this->masker = $masker;
    }

    public function getMasker(): LogValueMaskerInterface
    {
        return $this->masker;
    }

    /**
     * The request headers for ApiLoggerInterface, one "Name: value" per line
     *
     * @param array<mixed> $headers
     * @param list<string> $authHeaders header names read by the registered authorizations
     * @param list<string> $authCookies cookie names read by the registered authorizations
     */
    public function headers(array $headers, array $authHeaders = [], array $authCookies = []): string
    {
        $masked = $this->headers + ($this->headersOn ? self::keyed($authHeaders) : []);
        $result = '';
        foreach ($headers as $name => $value) {
            $name = (string)$name;
            $value = is_scalar($value) ? (string)$value : '';
            if ($this->isMaskedHeader($name, $masked)) {
                $value = $this->masker->mask($value);
            } elseif ($this->headersOn && strtolower($name) === 'cookie' && $authCookies !== []) {
                $value = $this->cookies($value, $authCookies);
            }
            if ($value !== null) {
                $result .= sprintf('%s: %s%s', $name, $value, PHP_EOL);
            }
        }
        return $result;
    }

    /**
     * The request URI for ApiLoggerInterface with the values of masked query parameters replaced
     *
     * @param list<string> $authParams query parameter names read by the registered authorizations
     */
    public function uri(string $uri, array $authParams = []): string
    {
        if (!$this->queryParamsOn || !str_contains($uri, '?')) {
            return $uri;
        }
        [$path, $query] = explode('?', $uri, 2);
        $masked = $this->queryParams + self::keyed($authParams);
        $parts = [];
        foreach (explode('&', $query) as $part) {
            $pair = explode('=', $part, 2);
            $name = strtolower(urldecode($pair[0]));
            $bracket = strpos($name, '[');
            $name = $bracket === false ? $name : substr($name, 0, $bracket);
            if (count($pair) === 2 && isset($masked[$name])) {
                $value = $this->masker->mask(urldecode($pair[1]));
                if ($value === null) {
                    continue;
                }
                $part = $pair[0] . '=' . str_replace(['%2A', '%3A'], ['*', ':'], rawurlencode($value));
            }
            $parts[] = $part;
        }
        return $parts === [] ? $path : $path . '?' . implode('&', $parts);
    }

    /**
     * @param array<string, string> $masked
     */
    private function isMaskedHeader(string $name, array $masked): bool
    {
        $name = strtolower($name);
        if (isset($masked[$name])) {
            return true;
        }
        return array_any($this->headerPatterns, fn(string $_, $pattern): bool => self::matches($name, $pattern));
    }

    /**
     * @param list<string> $cookies
     */
    private function cookies(string $header, array $cookies): string
    {
        $pairs = [];
        foreach (explode(';', $header) as $pair) {
            $pair = trim($pair);
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2 && in_array($parts[0], $cookies, true)) {
                $value = $this->masker->mask($parts[1]);
                if ($value === null) {
                    continue;
                }
                $pair = $parts[0] . '=' . $value;
            }
            $pairs[] = $pair;
        }
        return implode('; ', $pairs);
    }

    private static function matches(string $name, string $pattern): bool
    {
        return preg_match('~^' . str_replace('\*', '.*', preg_quote($pattern, '~')) . '$~', $name) === 1;
    }

    /**
     * Why $header cannot be masked, or null
     */
    public static function headerNameProblem(string $header): ?string
    {
        if (preg_match("~^[!#$%&'+.^_`|\~0-9A-Za-z-]+$~", $header) === 1) {
            return null;
        }
        if (str_contains($header, '*')) {
            return 'it contains *, which is only for header patterns. Add it as a pattern, or write the full header name';
        }
        return 'it is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, for example X-Session';
    }

    /**
     * Why $pattern is not a header pattern, or null
     */
    public static function headerPatternProblem(string $pattern): ?string
    {
        if (preg_match("~^[!#$%&'*+.^_`|\~0-9A-Za-z-]+$~", $pattern) !== 1) {
            return 'it is not a header pattern, a pattern is a header name with * for any characters and no spaces, colons or other separators, for example *token* or x-auth-*';
        }
        if (!str_contains($pattern, '*')) {
            return 'it has no *, so it matches only one header. Add it as a header name, or use * for any characters, for example *token*';
        }
        if (trim($pattern, '*') === '') {
            return 'it matches every header, so nothing useful would be logged. Name a part of the header, for example *token*, or leave the headers out in your logger';
        }
        return null;
    }

    /**
     * Why $param is not a query parameter name, or null
     */
    public static function queryParamProblem(string $param): ?string
    {
        if ($param === '' || preg_match('~[\s=&#?\[\]]~', $param) === 1) {
            return 'it is not a query parameter name. Write only the name without =, &, ?, # or brackets, for example api_key (it also masks api_key[] and api_key[x])';
        }
        return null;
    }

    /**
     * @param array<string> $list
     * @param array<string> $patterns
     */
    public static function notInListProblem(string $value, array $list, string $what, array $patterns = []): string
    {
        $message = sprintf(
            '%s is not in the masked %s (%s), so there is nothing to remove.%s',
            "'" . $value . "'",
            $what,
            $list === [] ? 'the list is empty' : implode(', ', $list),
            Hint::didYouMean($value, $list),
        );
        foreach ($patterns as $pattern) {
            if (self::matches(strtolower($value), strtolower($pattern))) {
                return $message . sprintf(" It is masked by the pattern '%s'; remove the pattern, or replace the patterns with a narrower list.", $pattern);
            }
        }
        return $message . ' Entries compare case-insensitively; fix the spelling or leave the entry out.';
    }

    private static function check(?string $problem, string $call, string $value, bool $inList = false): void
    {
        if ($problem !== null) {
            $where = $inList ? sprintf("%s(): '%s' in the list", $call, $value) : self::call($call, $value);
            throw new \InvalidArgumentException(sprintf('%s: %s.', $where, $problem));
        }
    }

    private static function call(string $call, string $value): string
    {
        return sprintf("%s('%s')", $call, $value);
    }

    /**
     * @param string[] $values
     * @return array<string, string>
     */
    private static function keyed(array $values): array
    {
        $keyed = [];
        foreach ($values as $value) {
            $keyed[strtolower($value)] = $value;
        }
        return $keyed;
    }
}
