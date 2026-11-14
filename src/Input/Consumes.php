<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Restricts the body media types of a TypedHandler.
 * Without it, a DTO with body fields accepts JSON and URL-encoded form. With a #[File] it accepts JSON and multipart/form-data;
 * multipart replaces URL-encoded form, which cannot carry files.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Consumes
{
    /** @var array<string> */
    public array $types;

    public function __construct(string ...$types)
    {
        $this->types = $types;
    }
}
