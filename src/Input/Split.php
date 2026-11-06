<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Splits a string into a list of strings. Nothing is trimmed and empty pieces are kept.
 */
final readonly class Split implements CasterInterface, OpenApiCasterInterface
{
    public function __construct(private string $separator)
    {
        if ($this->separator === '') {
            throw new \InvalidArgumentException("Split separator must not be empty. Use for example #[Cast(Split::class, ',')].");
        }
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function cast(mixed $value): array
    {
        if (!is_string($value)) {
            throw new CastException(sprintf('Value must be a string that can be split on %s', $this->separator));
        }

        return explode($this->separator, $value);
    }

    /**
     * @return array{type: string, example: string}
     */
    #[\Override]
    public function openApiSchema(): array
    {
        return ['type' => 'string', 'example' => implode($this->separator, ['red', 'green', 'blue'])];
    }
}
