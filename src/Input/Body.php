<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Reads a promoted DTO property from a JSON, form or multipart body field.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Body
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
