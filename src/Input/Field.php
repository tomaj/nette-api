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
        public bool $nullable,
        public bool $hasDefault,
        public mixed $default,
        public ?string $description,
        public array $examples,
        public ?string $listItem,
        public ?string $enumClass,
        public bool $enumInt,
        public array $enumValues,
        public array $constraints,
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

    public function readsBody(): bool
    {
        return $this->source === Source::Body || $this->source === Source::File;
    }
}
