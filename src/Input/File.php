<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Reads an uploaded file into a FileUpload (or a list of them). Multipart only.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class File
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
