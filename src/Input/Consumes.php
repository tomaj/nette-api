<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Restricts the body media types of a TypedHandler.
 * Without it, a DTO with body fields accepts JSON and URL-encoded form, plus multipart when it has a file.
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
