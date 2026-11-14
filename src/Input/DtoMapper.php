<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\Length;

/**
 * Builds one DTO's values from the current request and collects every field error.
 */
final class DtoMapper
{
    /** One request reports at most this many errors, however many lists and objects are nested. The rest of the body is not checked. */
    public const int MAX_ERRORS = 100;

    private bool $done = false;

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** Errors produced so far in this request, nested ones included. */
    private int $errorCount = 0;

    /** Something was left unchecked because the budget was spent. */
    private bool $skipped = false;

    public function __construct(private readonly DtoMetadata $metadata)
    {
    }

    public function value(Field $field): mixed
    {
        $this->evaluate();
        if (array_key_exists($field->property, $this->values)) {
            return $this->values[$field->property];
        }

        return $field->hasDefault ? $field->default : null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(Field $field): array
    {
        $this->evaluate();
        $own = [];
        $name = $field->externalName;
        foreach ($this->errors as $path => $messages) {
            if ($path === $name || str_starts_with($path, sprintf('%s.', $name)) || str_starts_with($path, sprintf('%s[', $name))) {
                $own[$path] = $messages;
            }
        }
        // errors of the request as a whole belong to one field, so that they are reported once: the first body field, or the first field when nothing reads a body
        if ($field->property === ($this->metadata->firstBody ?? $this->metadata->fields[0]->property) && isset($this->errors['body'])) {
            $own['body'] = $this->errors['body'];
        }

        return $own;
    }

    private function evaluate(): void
    {
        if ($this->done) {
            return;
        }
        $this->done = true;

        RequestInput::boot($this->metadata->handler, $this->metadata->contentTypes, $this->metadata->bodyRequired, $this->metadata->readsBody);
        $failure = RequestInput::failure();
        $converted = [];
        foreach ($this->untilTruncated($this->metadata->fields) as $field) {
            if ($failure !== null && $field->readsBody()) {
                continue;
            }
            $result = $this->convert($field, RequestInput::read($field), $field->externalName);
            if ($result['errors'] !== []) {
                self::merge($this->errors, $result['errors'], $result['value']);
                continue;
            }
            $converted[$field->property] = $result['value'];
        }

        $properties = array_map(static fn (Field $field): string => $field->property, $this->metadata->fields);
        foreach ($this->metadata->fields as $field) {
            if (!array_key_exists($field->property, $converted) || $converted[$field->property] === null) {
                continue;
            }
            foreach ($this->messages($field, $converted[$field->property], $field->externalName, $properties, $converted) as $message) {
                $this->errors[$field->externalName][] = $message;
            }
        }
        if ($failure !== null) {
            $this->errors['body'][] = $failure;
        }
        if ($this->skipped) {
            // under `body` because it is about the request as a whole, not about the field that happened to use the last of the budget
            $this->errors['body'][] = sprintf('Only the first %d errors are reported, the rest of the request was not checked', self::MAX_ERRORS);
        }
        $this->values = $converted;
    }

    /**
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function convert(Field $field, mixed $raw, string $path): array
    {
        if ($raw instanceof MissingValue) {
            if ($field->hasDefault) {
                return ['value' => $field->default, 'errors' => []];
            }
            if ($field->nullable) {
                return ['value' => null, 'errors' => []];
            }

            if ($field->source === Source::File && RequestInput::mediaType() !== MediaTypes::MULTIPART) {
                return $this->fail($path, 'File is required and can only be uploaded as multipart/form-data, but the request is ' . RequestInput::mediaType());
            }

            return $this->fail($path, 'Field is required');
        }
        if ($raw === null) {
            if ($field->nullable) {
                return ['value' => null, 'errors' => []];
            }

            return $this->fail($path, 'Value must not be null');
        }

        if ($field->casterClass !== null) {
            try {
                $caster = new ($field->casterClass)(...$field->casterArguments);
                $value = $caster->cast($raw);
            } catch (CastException $exception) {
                return $this->fail($path, $exception->getMessage());
            }

            return ['value' => $value, 'errors' => []];
        }

        if ($field->source === Source::File || $field->typeName === FileUpload::class) {
            return $this->file($field, $raw, $path);
        }
        if ($field->item instanceof Field) {
            return $this->list($field, $raw, $path);
        }
        if ($field->children !== []) {
            return $this->object($field, $raw, $path);
        }

        try {
            return ['value' => $this->scalar($field, $raw), 'errors' => []];
        } catch (ConversionFailed $exception) {
            return $this->fail($path, $exception->getMessage());
        }
    }

    /**
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function object(Field $field, mixed $raw, string $path): array
    {
        if ($raw instanceof \stdClass) {
            $raw = get_object_vars($raw);
        } elseif (!is_array($raw) || array_is_list($raw)) {
            return $this->fail($path, 'Value must be an object');
        }

        $converted = [];
        $errors = [];
        foreach ($this->untilTruncated($field->children) as $child) {
            $childRaw = array_key_exists($child->externalName, $raw) ? $raw[$child->externalName] : new MissingValue();
            $result = $this->convert($child, $childRaw, sprintf('%s.%s', $path, $child->externalName));
            if ($result['errors'] !== []) {
                self::merge($errors, $result['errors'], $result['value']);
                continue;
            }
            $converted[$child->property] = $result['value'];
        }

        $properties = array_map(static fn (Field $child): string => $child->property, $field->children);
        foreach ($field->children as $child) {
            if (!array_key_exists($child->property, $converted) || $converted[$child->property] === null) {
                continue;
            }
            $childPath = sprintf('%s.%s', $path, $child->externalName);
            foreach ($this->messages($child, $converted[$child->property], $childPath, $properties, $converted) as $message) {
                $errors[$childPath][] = $message;
            }
        }
        if ($errors !== []) {
            return ['value' => null, 'errors' => $errors];
        }

        return ['value' => $this->instantiate($field->typeName, $field->children, $converted), 'errors' => []];
    }

    /**
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function list(Field $field, mixed $raw, string $path): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return $this->fail($path, 'Value must be a list');
        }

        // the item count alone decides Length, so a list of the wrong size is rejected before its items are converted
        foreach ($field->constraints as $constraint) {
            $message = $constraint instanceof Length ? $constraint->validate($raw, new ConstraintContext($path, $field->dtoClass, [], [])) : null;
            if ($message !== null) {
                return $this->fail($path, $message);
            }
        }

        $item = $field->itemField();

        $values = [];
        $errors = [];
        $index = 0;
        foreach ($this->untilTruncated($raw) as $value) {
            $result = $this->convert($item, $value, sprintf('%s[%d]', $path, $index++));
            if ($result['errors'] !== []) {
                self::merge($errors, $result['errors'], $result['value']);
                continue;
            }
            $values[] = $result['value'];
        }
        if ($errors !== []) {
            return ['value' => null, 'errors' => $errors];
        }

        return ['value' => $values, 'errors' => []];
    }

    /**
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function file(Field $field, mixed $raw, string $path): array
    {
        if ($field->listItem === FileUpload::class) {
            if (!is_array($raw)) {
                return $this->fail($path, 'Value must be a file upload');
            }
            $uploads = [];
            foreach (self::fileList($raw) as $file) {
                $uploads[] = FileUpload::fromFile($file);
            }

            return ['value' => $uploads, 'errors' => []];
        }

        if (is_array($raw) && is_array($raw['name'] ?? null)) {
            return $this->fail($path, 'Value must be a file upload');
        }

        return ['value' => FileUpload::fromFile(self::fileEntry(is_array($raw) ? $raw : [])), 'errors' => []];
    }

    /**
     * @param array<mixed> $file
     * @return array<array-key, array<array-key, mixed>>
     */
    private static function fileList(array $file): array
    {
        if (!is_array($file['name'] ?? null)) {
            return [self::fileEntry($file)];
        }

        $result = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
            $values = $file[$key] ?? null;
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $index => $value) {
                $result[$index][$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $file
     * @return array<array-key, mixed>
     */
    private static function fileEntry(array $file): array
    {
        $entry = [];
        foreach ($file as $key => $value) {
            $entry[$key] = $value;
        }

        return $entry;
    }

    private function scalar(Field $field, mixed $raw): mixed
    {
        if ($field->enumClass !== null) {
            return ScalarCoercer::toEnum($raw, $field->enumClass);
        }

        if ($field->typeName === 'int') {
            return ScalarCoercer::toInt($raw);
        }
        if ($field->typeName === 'float') {
            return ScalarCoercer::toFloat($raw);
        }
        if ($field->typeName === 'bool') {
            return ScalarCoercer::toBool($raw);
        }
        if ($field->typeName === 'string') {
            return ScalarCoercer::toString($raw);
        }

        return ScalarCoercer::toDateTime($raw);
    }

    /**
     * @param list<Field> $children
     * @param array<string, mixed> $values
     */
    private function instantiate(string $class, array $children, array $values): object
    {
        $arguments = [];
        foreach ($children as $child) {
            $arguments[$child->property] = $values[$child->property];
        }

        return new $class(...$arguments);
    }

    /**
     * @param list<string> $properties
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private function messages(Field $field, mixed $value, string $path, array $properties, array $values): array
    {
        $context = new ConstraintContext($path, $field->dtoClass, $properties, $values);
        $messages = [];
        foreach ($field->constraints as $constraint) {
            $message = $constraint->validate($value, $context);
            if ($message !== null) {
                $messages[] = $message;
            }
        }

        $this->errorCount += count($messages);

        return $messages;
    }

    /**
     * The items of a list, the children of an object or the fields of the DTO, up to the point where the error budget is spent.
     *
     * @template TValue
     * @param array<array-key, TValue> $items
     * @return \Generator<int, TValue>
     */
    private function untilTruncated(array $items): \Generator
    {
        foreach ($items as $item) {
            if ($this->errorCount >= self::MAX_ERRORS) {
                $this->skipped = true;

                return;
            }
            yield $item;
        }
    }

    /**
     * @return array{value: null, errors: array<string, list<string>>}
     */
    private function fail(string $path, string $message): array
    {
        ++$this->errorCount;

        return ['value' => null, 'errors' => [$path => [$message]]];
    }

    /**
     * Appends in place: copying the whole error array for every bad item made a long invalid list quadratic.
     *
     * @param array<string, list<string>> $left
     * @param array<string, list<string>> $right
     */
    private static function merge(array &$left, array $right, mixed $value): void
    {
        if ($value !== null) {
            throw new \LogicException('A field error is reported without discarding the converted value.');
        }

        foreach ($right as $path => $messages) {
            foreach ($messages as $message) {
                $left[$path][] = $message;
            }
        }
    }
}
