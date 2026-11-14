<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Reads a promoted DTO property from the query string. The external name defaults to the property name.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Query
{
    /**
     * @param array<array-key, mixed> $examples named examples, the same pairs as InputParam::addExample()
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public array $examples = [],
    ) {
    }
}
