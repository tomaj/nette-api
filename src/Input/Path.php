<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Reads a promoted DTO property from a {name} segment of the endpoint URL.
 * A path property is required: it cannot be nullable and cannot have a default.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Path
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
