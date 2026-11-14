<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Reads a promoted DTO property from an HTTP header. Lookup is case-insensitive.
 * #[Header('X-Request-Id')] reads that header, not a header named after the property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Header
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
