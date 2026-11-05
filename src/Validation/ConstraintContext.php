<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

/**
 * The property a constraint is checking, and the other converted values of the same DTO.
 */
final readonly class ConstraintContext
{
    /**
     * @param list<string> $properties property names of the DTO, in constructor order
     * @param array<string, mixed> $values converted sibling values keyed by property name; a failed sibling is absent
     */
    public function __construct(
        public string $path,
        public string $dtoClass,
        private array $properties,
        private array $values,
    ) {
    }

    /**
     * A sibling value of the same DTO, by its PHP property name.
     * A property that was omitted or failed conversion is null. An unknown name is a programmer error.
     */
    public function value(string $property): mixed
    {
        if (!in_array($property, $this->properties, true)) {
            throw new \LogicException(sprintf(
                "Constraint at '%s' reads property '%s', but %s has no such property. It has: %s. Use the PHP property name, for example value('%s').",
                $this->path,
                $property,
                $this->dtoClass,
                implode(', ', $this->properties),
                $this->properties[0] ?? 'startsAt',
            ));
        }

        return $this->values[$property] ?? null;
    }
}
