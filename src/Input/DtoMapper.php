<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Validation\ConstraintContext;

/**
 * Builds one DTO's values from the current request and collects every field error.
 */
final class DtoMapper
{
    private bool $done = false;

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, list<string>> */
    private array $errors = [];

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
        if ($field->property === $this->metadata->firstBody && isset($this->errors['body'])) {
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
        foreach ($this->metadata->fields as $field) {
            if ($failure !== null && $field->readsBody()) {
                continue;
            }
            $result = $this->convert($field, RequestInput::read($field), $field->externalName);
            if ($result['errors'] !== []) {
                $this->errors = self::merge($this->errors, $result['errors'], $result['value']);
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
            $this->errors['body'] = [$failure];
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

            return ['value' => null, 'errors' => [$path => ['Field is required']]];
        }
        if ($raw === null) {
            if ($field->nullable) {
                return ['value' => null, 'errors' => []];
            }

            return ['value' => null, 'errors' => [$path => ['Value must not be null']]];
        }

        if ($field->casterClass !== null) {
            try {
                $caster = new ($field->casterClass)(...$field->casterArguments);
                $value = $caster->cast($raw);
            } catch (CastException $exception) {
                return ['value' => null, 'errors' => [$path => [$exception->getMessage()]]];
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
            return ['value' => null, 'errors' => [$path => [$exception->getMessage()]]];
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
            return ['value' => null, 'errors' => [$path => ['Value must be an object']]];
        }

        $converted = [];
        $errors = [];
        foreach ($field->children as $child) {
            $childRaw = array_key_exists($child->externalName, $raw) ? $raw[$child->externalName] : new MissingValue();
            $result = $this->convert($child, $childRaw, sprintf('%s.%s', $path, $child->externalName));
            if ($result['errors'] !== []) {
                $errors = self::merge($errors, $result['errors'], $result['value']);
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
            return ['value' => null, 'errors' => [$path => ['Value must be a list']]];
        }

        $item = $field->itemField();

        $values = [];
        $errors = [];
        foreach ($raw as $index => $value) {
            $result = $this->convert($item, $value, sprintf('%s[%s]', $path, (string) $index));
            if ($result['errors'] !== []) {
                $errors = self::merge($errors, $result['errors'], $result['value']);
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
                return ['value' => null, 'errors' => [$path => ['Value must be a file upload']]];
            }
            $uploads = [];
            foreach (self::fileList($raw) as $file) {
                $uploads[] = FileUpload::fromFile($file);
            }

            return ['value' => $uploads, 'errors' => []];
        }

        if (is_array($raw) && is_array($raw['name'] ?? null)) {
            return ['value' => null, 'errors' => [$path => ['Value must be a file upload']]];
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

        return $messages;
    }

    /**
     * @param array<string, list<string>> $left
     * @param array<string, list<string>> $right
     * @return array<string, list<string>>
     */
    private static function merge(array $left, array $right, mixed $value): array
    {
        if ($value !== null) {
            throw new \LogicException('A field error is reported without discarding the converted value.');
        }

        foreach ($right as $path => $messages) {
            foreach ($messages as $message) {
                $left[$path][] = $message;
            }
        }

        return $left;
    }
}
