<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Params\HeaderBag;
use Tomaj\NetteApi\Params\PathValues;

/**
 * Raw request data for the handler that is running. One read of the body is shared by every field.
 */
final class RequestInput
{
    /** How much of a charset name an error message repeats. */
    private const int CHARSET_SHOWN = 30;

    private const string TOO_MANY_FIELDS = 'Too many form fields';

    private static ?string $handler = null;

    /** @var array<array-key, mixed> */
    private static array $query = [];

    /** @var array<array-key, mixed> */
    private static array $body = [];

    /** @var array<array-key, mixed> */
    private static array $files = [];

    private static ?string $failure = null;

    private static ?string $mediaType = null;

    private static ?string $rawOverride = null;

    public static function reset(): void
    {
        self::$handler = null;
        self::$query = [];
        self::$body = [];
        self::$files = [];
        self::$failure = null;
        self::$mediaType = null;
        self::$rawOverride = null;
    }

    /**
     * Replaces php://input. Used by tests; a real request leaves this unset.
     *
     * @internal
     */
    public static function setRawBody(string $raw): void
    {
        self::$rawOverride = $raw;
    }

    /**
     * @param list<string> $consumes
     */
    public static function boot(string $handler, array $consumes, bool $bodyRequired, bool $readsBody): void
    {
        if (self::$handler === $handler) {
            return;
        }

        self::$handler = $handler;
        self::$query = self::stringMap($_GET);
        self::$body = [];
        self::$files = [];
        self::$failure = null;
        self::$mediaType = null;
        if ($readsBody) {
            self::loadBody($consumes, $bodyRequired);
        }
    }

    public static function failure(): ?string
    {
        return self::$failure;
    }

    /**
     * The accepted body media type without parameters, or null when the request has no body.
     */
    public static function mediaType(): ?string
    {
        return self::$mediaType;
    }

    public static function read(Field $field): mixed
    {
        return match ($field->source) {
            Source::Query => self::value(self::$query, $field->externalName),
            Source::Header => self::header($field->externalName),
            Source::Path => self::path($field->externalName),
            Source::File => self::file($field->externalName),
            Source::Body => self::value(self::$body, $field->externalName),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function value(array $data, string $name): mixed
    {
        if (!array_key_exists($name, $data)) {
            return new MissingValue();
        }

        return $data[$name];
    }

    private static function header(string $name): mixed
    {
        if (!HeaderBag::has($name)) {
            return new MissingValue();
        }

        return HeaderBag::get($name);
    }

    private static function path(string $name): mixed
    {
        if (!PathValues::has($name)) {
            return new MissingValue();
        }

        return PathValues::get($name);
    }

    private static function file(string $name): mixed
    {
        if (!array_key_exists($name, self::$files)) {
            return new MissingValue();
        }

        $file = self::$files[$name];
        if (!is_array($file)) {
            return new MissingValue();
        }
        $nameValue = $file['name'] ?? null;
        $error = $file['error'] ?? null;
        $empty = is_array($nameValue)
            ? is_array($error) && $error !== [] && array_filter($error, static fn (mixed $slot): bool => $slot !== \UPLOAD_ERR_NO_FILE) === []
            : $error === \UPLOAD_ERR_NO_FILE && ($nameValue ?? '') === '';

        return $empty ? new MissingValue() : $file;
    }

    /**
     * @param list<string> $consumes
     */
    private static function loadBody(array $consumes, bool $bodyRequired): void
    {
        $header = $_SERVER['CONTENT_TYPE'] ?? null;
        if (!is_string($header)) {
            $header = isset($_SERVER['HTTP_CONTENT_TYPE']) && is_string($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : null;
        }
        $type = MediaTypes::normalize($header);
        if ($type === '') {
            // a body that arrives without a type is never read silently: it would be dropped without a word
            $raw = self::rawBody();
            if ($bodyRequired || (is_string($raw) && $raw !== '')) {
                throw UnsupportedMediaTypeException::missing($consumes);
            }

            return;
        }
        if (!in_array($type, $consumes, true)) {
            throw UnsupportedMediaTypeException::received($type, $consumes);
        }
        self::$mediaType = $type;
        if ($type === MediaTypes::JSON) {
            self::$failure = self::charsetFailure($header ?? '');
            if (self::$failure === null) {
                self::loadJson();
            }

            return;
        }

        self::loadForm();
    }

    /**
     * JSON is UTF-8 (RFC 8259), so another charset would be decoded wrongly instead of being ignored.
     */
    private static function charsetFailure(string $header): ?string
    {
        // parameters are read like RFC 9110 does: a quoted value may contain `;` and `charset=`
        preg_match_all('/;\s*([^=;\s]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (strtolower($match[1]) !== 'charset') {
                continue;
            }
            $charset = strtolower(trim($match[2], "\" \t"));

            // us-ascii is a subset of UTF-8, and utf8 is how many clients spell it
            return in_array($charset, ['utf-8', 'utf8', 'us-ascii'], true)
                ? null
                : sprintf('JSON must be UTF-8; the request says charset=%s', substr($charset, 0, self::CHARSET_SHOWN));
        }

        return null;
    }

    private static function loadJson(): void
    {
        $raw = self::rawBody();
        if ($raw === false) {
            self::$failure = 'Unable to read the request body';
            return;
        }
        if ($raw === '') {
            return;
        }

        $decoded = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::$failure = json_last_error_msg();
            return;
        }
        if (!$decoded instanceof \stdClass) {
            self::$failure = 'Value must be an object';
            return;
        }

        self::$body = self::objectProperties($decoded);
    }

    private static function loadForm(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' || $_POST !== [] || $_FILES !== []) {
            self::$body = self::stringMap($_POST);
            self::$files = self::stringMap($_FILES);
            // PHP drops the variables over max_input_vars without a word, so a form that was cut is found by parsing its body again
            if (self::$mediaType === MediaTypes::FORM && count($_POST, COUNT_RECURSIVE) >= self::maxInputVars()) {
                $raw = self::rawBody();
                if (is_string($raw) && self::parseForm($raw) === null) {
                    self::$failure = self::TOO_MANY_FIELDS;
                    self::$body = [];
                }
            }

            return;
        }

        $raw = self::rawBody();
        $parsed = [];
        if (is_string($raw) && self::$mediaType === MediaTypes::MULTIPART && $raw !== '') {
            self::$failure = sprintf('multipart/form-data is only parsed by PHP for POST requests, but this one is %s. Send it with POST, or use application/json', TypeHelper::toString($method));
        } elseif (is_string($raw)) {
            $parsed = self::parseForm($raw);
            if ($parsed === null) {
                self::$failure = self::TOO_MANY_FIELDS;
                $parsed = [];
            }
        }
        self::$body = self::stringMap($parsed);
        self::$files = self::stringMap($_FILES);
    }

    private static function maxInputVars(): int
    {
        return (int) ini_get('max_input_vars');
    }

    /**
     * @return array<array-key, mixed>|null null when the body has more variables than max_input_vars
     */
    private static function parseForm(string $raw): ?array
    {
        $exceeded = false;
        // PHP warns when the body has more variables than max_input_vars; that is a client mistake, not a server warning
        set_error_handler(static function () use (&$exceeded): bool {
            $exceeded = true;

            return true;
        }, E_WARNING);
        parse_str($raw, $parsed);
        restore_error_handler();

        return $exceeded ? null : $parsed;
    }

    /**
     * Top-level properties of a JSON object. Nested objects stay stdClass and nested arrays stay lists,
     * so {} and [] remain different when a field is mapped.
     *
     * @return array<array-key, mixed>
     */
    private static function objectProperties(\stdClass $object): array
    {
        $mapped = [];
        foreach (get_object_vars($object) as $key => $value) {
            $mapped[$key] = $value;
        }

        return $mapped;
    }

    /**
     * @param array<mixed> $values
     * @return array<array-key, mixed>
     */
    private static function stringMap(array $values): array
    {
        $mapped = [];
        foreach ($values as $key => $value) {
            $mapped[$key] = $value;
        }

        return $mapped;
    }

    public static function rawContents(): string|false
    {
        return self::rawBody();
    }

    private static function rawBody(): string|false
    {
        if (self::$rawOverride !== null) {
            return self::$rawOverride;
        }

        return file_get_contents('php://input');
    }
}
