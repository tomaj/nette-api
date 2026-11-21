<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Nette\Utils\Strings;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Params\HeaderBag;
use Tomaj\NetteApi\Params\PathValues;

/**
 * Raw request data of the current request. DtoMapper boots it once per handler params, so every field shares one read of the body.
 */
final class RequestInput
{
    /** How much of a charset name an error message repeats. */
    private const int CHARSET_SHOWN = 30;

    private const string TOO_MANY_FIELDS = 'Too many form fields';

    /** @var array<array-key, mixed> */
    private static array $query = [];

    /** @var array<array-key, mixed> */
    private static array $body = [];

    /** @var array<array-key, mixed> */
    private static array $files = [];

    private static ?string $failure = null;

    private static ?string $mediaType = null;

    private static ?string $rawOverride = null;

    /** @var array{key: list<mixed>, body: array<array-key, mixed>, failure: ?string}|null the body read for BodyInputParam */
    private static ?array $plain = null;

    /**
     * Forgets what was read from the previous request. ApiPresenter calls it when a request starts; a body set by setRawBody() stays.
     */
    public static function reset(): void
    {
        self::$query = [];
        self::$body = [];
        self::$files = [];
        self::$failure = null;
        self::$mediaType = null;
        self::$plain = null;
    }

    /**
     * Replaces php://input, null reads it again. Used by tests; a real request leaves this unset.
     *
     * @internal
     */
    public static function setRawBody(?string $raw): void
    {
        self::$rawOverride = $raw;
        self::$plain = null;
    }

    /**
     * @param list<string> $consumes
     */
    public static function boot(array $consumes, bool $bodyRequired, bool $readsBody): void
    {
        self::$query = $_GET;
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
     * The body fields for BodyInputParam of a params() handler, read like a typed handler reads them and once per request.
     *
     * @param list<string> $consumes
     * @return array{body: array<array-key, mixed>, failure: ?string}
     * @throws UnsupportedMediaTypeException
     */
    public static function plainBody(array $consumes): array
    {
        // a test or a worker that changes the request without reset() still gets this request's body
        $key = [HeaderBag::get('Content-Type'), HeaderBag::get('Content-Length'), $_SERVER['REQUEST_METHOD'] ?? null, $_POST, $consumes];
        if (self::$plain !== null && self::$plain['key'] === $key) {
            return self::$plain;
        }

        // a typed handler reads the request in one go when it is first validated, so booting again here does not disturb it
        self::boot($consumes, false, true);

        return self::$plain = ['key' => $key, 'body' => self::$body, 'failure' => self::$failure];
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
        $header = HeaderBag::get('Content-Type');
        $type = MediaTypes::normalize($header);
        if ($type === '' || !in_array($type, $consumes, true)) {
            // a request without a body is fine when nothing in the body is required, whatever its Content-Type says;
            // a body that cannot be read is never dropped silently
            if (!$bodyRequired && self::bodyless()) {
                return;
            }

            throw $type === '' ? UnsupportedMediaTypeException::missing($consumes) : UnsupportedMediaTypeException::received($type, $consumes);
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
            // PHP 8.6 appends " near location line:column"; it is dropped so the error reads the same on every PHP version
            self::$failure = Strings::replace(json_last_error_msg(), '/ near location \d+:\d+$/', '');
            return;
        }
        if (!$decoded instanceof \stdClass) {
            self::$failure = 'Value must be an object';
            return;
        }

        // nested objects stay stdClass and nested arrays stay lists, so {} and [] remain different when a field is mapped
        self::$body = get_object_vars($decoded);
    }

    private static function loadForm(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' || $_POST !== [] || $_FILES !== []) {
            if ($_POST === [] && $_FILES === [] && self::overPostMaxSize()) {
                // PHP drops a body over post_max_size and leaves $_POST and $_FILES empty
                self::$failure = 'Request body is too large';
            }
            self::$body = $_POST;
            self::$files = $_FILES;
            // PHP drops the variables over max_input_vars without a word, so a form that was cut is found by parsing its body again;
            // a multipart body cannot be parsed again, so one that reaches the limit is taken as cut
            if (self::fieldCount($_POST) >= self::maxInputVars()) {
                $raw = self::rawBody();
                if (self::$mediaType === MediaTypes::MULTIPART || (is_string($raw) && self::parseForm($raw) === null)) {
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
        self::$body = $parsed;
        self::$files = $_FILES;
    }

    private static function overPostMaxSize(): bool
    {
        $limit = ini_parse_quantity(TypeHelper::toString(ini_get('post_max_size')));
        $length = HeaderBag::get('Content-Length');

        // post_max_size 0 means no limit
        return is_numeric($length) && $length > ($limit ?: PHP_INT_MAX);
    }

    private static function bodyless(): bool
    {
        // PHP moves a multipart body into $_POST and $_FILES, so php://input is empty for it
        return $_POST === [] && $_FILES === [] && in_array(self::rawBody(), ['', false], true);
    }

    /**
     * The values of a form, which is what max_input_vars counts: `a[]=1&a[]=2` is two.
     *
     * @param array<array-key, mixed> $fields
     */
    private static function fieldCount(array $fields): int
    {
        $count = 0;
        array_walk_recursive($fields, static function () use (&$count): void {
            $count++;
        });

        return $count;
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
