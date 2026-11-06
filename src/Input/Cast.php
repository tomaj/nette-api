<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Replaces the built-in conversion of one property with a caster. Constraints run on the cast value.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Cast
{
    /** @var array<mixed> */
    public array $arguments;

    /**
     * @param class-string<CasterInterface> $caster
     */
    public function __construct(public string $caster, mixed ...$arguments)
    {
        $this->arguments = $arguments;
    }
}
