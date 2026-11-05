<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Params\HeaderBag;
use Tomaj\NetteApi\Params\PathValues;

/**
 * Raw request data for the handler that is running. One read of the body is shared by every field.
 */
final class RequestInput
{
    private static ?string $handler = null;

    /** @var array<array-key, mixed> */
    private static array $query = [];

    /** @var array<array-key, mixed> */
    private static array $body = [];

    /** @var array<array-key, mixed> */
    private static array $files = [];

    private static ?string $failure = null;

    private static ?string $rawOverride = null;

    public static function reset(): void
    {
        self::$handler = null;
        self::$query = [];
        self::$body = [];
        self::$files = [];
        self::$failure = null;
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
        if ($readsBody) {
            self::loadBody($consumes, $bodyRequired);
        }
    }

    public static function failure(): ?string
    {
        return self::$failure;
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
        if (($file['error'] ?? null) === \UPLOAD_ERR_NO_FILE && !is_array($nameValue) && ($nameValue ?? '') === '') {
            return new MissingValue();
        }

        return $file;
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
            if ($bodyRequired) {
                throw UnsupportedMediaTypeException::missing($consumes);
            }

            return;
        }
        if (!in_array($type, $consumes, true)) {
            throw UnsupportedMediaTypeException::received($type, $consumes);
        }
        if ($type === MediaTypes::JSON) {
            self::loadJson();
            return;
        }

        self::loadForm();
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
            return;
        }

        $raw = self::rawBody();
        $parsed = [];
        if (is_string($raw)) {
            parse_str($raw, $parsed);
        }
        self::$body = self::stringMap($parsed);
        self::$files = self::stringMap($_FILES);
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
