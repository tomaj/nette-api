<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Validation\ConstraintInterface;

/**
 * One promoted DTO property after compile-time checks.
 * Nested DTO fields are children; a list's single item shape is $item.
 */
final readonly class Field
{
    /**
     * @param array<string, mixed> $examples
     * @param list<int|string> $enumValues
     * @param list<ConstraintInterface> $constraints
     * @param list<array{class: class-string<ConstraintInterface>, args: array<int|string, mixed>}> $constraintSpecs
     * @param array<mixed> $casterArguments
     * @param array<string, mixed>|null $casterSchema
     * @param list<self> $children
     * @param class-string<\BackedEnum>|null $enumClass
     * @param class-string<CasterInterface>|null $casterClass
     */
    public function __construct(
        public string $property,
        public string $externalName,
        public Source $source,
        public string $typeName,
        public bool $builtin,
        public bool $nullable,
        public bool $hasDefault,
        public mixed $default,
        public ?string $description,
        public array $examples,
        public ?string $listItem,
        public bool $listItemBuiltin,
        public ?string $enumClass,
        public bool $enumInt,
        public array $enumValues,
        public array $constraints,
        public array $constraintSpecs,
        public ?string $casterClass,
        public array $casterArguments,
        public ?array $casterSchema,
        public array $children,
        public ?self $item,
        public string $dtoClass,
    ) {
    }

    public function required(): bool
    {
        return !$this->nullable && !$this->hasDefault;
    }

    public function itemField(): self
    {
        if (!$this->item instanceof self) {
            throw new \LogicException(sprintf(
                'List property %s::$%s has no item type. Declare it with /** @param list<T> $%s */ using list<string>, list<int>, list<float>, list<bool>, list<FileUpload> or a DTO.',
                $this->dtoClass,
                $this->property,
                $this->property,
            ));
        }

        return $this->item;
    }

    public function readsBody(): bool
    {
        return $this->source === Source::Body || $this->source === Source::File;
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'property' => $this->property,
            'externalName' => $this->externalName,
            'source' => $this->source->value,
            'typeName' => $this->typeName,
            'builtin' => $this->builtin,
            'nullable' => $this->nullable,
            'hasDefault' => $this->hasDefault,
            'default' => ValueExport::export($this->default),
            'description' => $this->description,
            'examples' => ValueExport::export($this->examples),
            'listItem' => $this->listItem,
            'listItemBuiltin' => $this->listItemBuiltin,
            'enumClass' => $this->enumClass,
            'enumInt' => $this->enumInt,
            'enumValues' => $this->enumValues,
            'constraintSpecs' => ValueExport::export($this->constraintSpecs),
            'casterClass' => $this->casterClass,
            'casterArguments' => ValueExport::export($this->casterArguments),
            'casterSchema' => $this->casterSchema,
            'children' => array_map(static fn (self $child): array => $child->export(), $this->children),
            'item' => $this->item?->export(),
            'dtoClass' => $this->dtoClass,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function import(array $data): self
    {
        $constraints = self::importConstraints($data);
        $children = [];
        foreach (self::rows($data, 'children') as $child) {
            $children[] = self::import(self::stringKeyed($child));
        }
        $item = $data['item'] ?? null;
        $enumClass = self::nullableString($data, 'enumClass');
        if ($enumClass !== null && !is_a($enumClass, \BackedEnum::class, true)) {
            throw new \LogicException(sprintf("Compiled field metadata has enum '%s', which is not a backed enum. Rebuild the DI container.", $enumClass));
        }
        $casterClass = self::nullableString($data, 'casterClass');
        if ($casterClass !== null && !is_a($casterClass, CasterInterface::class, true)) {
            throw new \LogicException(sprintf("Compiled field metadata has caster '%s', which does not implement %s. Rebuild the DI container.", $casterClass, CasterInterface::class));
        }

        return new self(
            self::string($data, 'property'),
            self::string($data, 'externalName'),
            Source::from(self::string($data, 'source')),
            self::string($data, 'typeName'),
            self::bool($data, 'builtin'),
            self::bool($data, 'nullable'),
            self::bool($data, 'hasDefault'),
            ValueExport::import($data['default'] ?? null),
            self::nullableString($data, 'description'),
            self::stringMap(ValueExport::import($data['examples'] ?? [])),
            self::nullableString($data, 'listItem'),
            self::bool($data, 'listItemBuiltin'),
            $enumClass,
            self::bool($data, 'enumInt'),
            self::enumValues($data),
            $constraints['instances'],
            $constraints['specs'],
            $casterClass,
            array_values(self::mixedList(ValueExport::import($data['casterArguments'] ?? []))),
            self::optionalMap($data['casterSchema'] ?? null),
            $children,
            is_array($item) ? self::import(self::stringKeyed($item)) : null,
            self::string($data, 'dtoClass'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array{instances: list<ConstraintInterface>, specs: list<array{class: class-string<ConstraintInterface>, args: array<int|string, mixed>}>}
     */
    private static function importConstraints(array $data): array
    {
        $imported = ValueExport::import($data['constraintSpecs'] ?? []);
        if (!is_array($imported)) {
            throw new \LogicException('Compiled field metadata is missing constraints. Rebuild the DI container.');
        }
        $instances = [];
        $specs = [];
        foreach ($imported as $spec) {
            if (!is_array($spec) || !is_string($spec['class'] ?? null) || !is_array($spec['args'] ?? null) || !is_a($spec['class'], ConstraintInterface::class, true)) {
                throw new \LogicException('Compiled constraint metadata is invalid. Rebuild the DI container.');
            }
            $class = $spec['class'];
            $args = [];
            foreach ($spec['args'] as $key => $argument) {
                $args[$key] = $argument;
            }
            $instances[] = new $class(...$args);
            $specs[] = ['class' => $class, 'args' => $args];
        }

        return ['instances' => $instances, 'specs' => $specs];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<array<mixed>>
     */
    private static function rows(array $data, string $key): array
    {
        $rows = $data[$key] ?? [];
        if (!is_array($rows)) {
            throw new \LogicException(sprintf('Compiled field metadata "%s" is not a list. Rebuild the DI container.', $key));
        }
        $list = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \LogicException(sprintf('Compiled field metadata "%s" has an item that is not an array. Rebuild the DI container.', $key));
            }
            $list[] = $row;
        }

        return $list;
    }

    /**
     * @param array<mixed> $data
     * @return array<array-key, mixed>
     */
    private static function stringKeyed(array $data): array
    {
        $keyed = [];
        foreach ($data as $key => $value) {
            $keyed[$key] = $value;
        }

        return $keyed;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \LogicException(sprintf('Compiled field metadata is missing "%s". Rebuild the DI container.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \LogicException(sprintf('Compiled field metadata "%s" must be a string or null. Rebuild the DI container.', $key));
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw new \LogicException(sprintf('Compiled field metadata "%s" must be a boolean. Rebuild the DI container.', $key));
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \LogicException('Compiled field metadata examples must be an array. Rebuild the DI container.');
        }
        $mapped = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \LogicException('Compiled field metadata examples must be named. Rebuild the DI container.');
            }
            $mapped[$key] = $item;
        }

        return $mapped;
    }

    /**
     * @param mixed $value
     * @return array<int|string, mixed>
     */
    private static function mixedList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \LogicException('Compiled field metadata arguments must be an array. Rebuild the DI container.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function optionalMap(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \LogicException('Compiled field metadata schema must be an array. Rebuild the DI container.');
        }
        $mapped = [];
        foreach ($value as $key => $item) {
            $mapped[(string) $key] = $item;
        }

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<int|string>
     */
    private static function enumValues(array $data): array
    {
        $values = $data['enumValues'] ?? [];
        if (!is_array($values)) {
            throw new \LogicException('Compiled field metadata enum values must be a list. Rebuild the DI container.');
        }
        $list = [];
        foreach ($values as $value) {
            if (!is_int($value) && !is_string($value)) {
                throw new \LogicException('Compiled field metadata enum values must be strings or integers. Rebuild the DI container.');
            }
            $list[] = $value;
        }

        return $list;
    }
}
