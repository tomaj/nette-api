<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * The attribute that says where a DTO property is read from: #[Query], #[Path], #[Header], #[Body] or #[File].
 */
interface SourceAttribute
{
    public ?string $name { get; }

    public ?string $description { get; }

    /** @var array<array-key, mixed> */
    public array $examples { get; }

    public function source(): Source;
}
