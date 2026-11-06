<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Parses a date string with an explicit PHP format into a DateTimeImmutable at midnight.
 * Impossible dates and trailing characters are rejected.
 */
final readonly class DateFormat implements CasterInterface, OpenApiCasterInterface
{
    public function __construct(private string $format)
    {
        if ($this->format === '') {
            throw new \InvalidArgumentException("DateFormat needs a PHP date format, for example #[Cast(DateFormat::class, 'd.m.Y')].");
        }
    }

    #[\Override]
    public function cast(mixed $value): \DateTimeImmutable
    {
        $example = $this->example();
        $message = 'Date must match ' . $this->format . ', for example ' . $example;
        if (!is_string($value) || $value === '') {
            throw new CastException($message);
        }

        $date = \DateTimeImmutable::createFromFormat(sprintf('!%s', $this->format), $value);
        if ($date === false || $date->format($this->format) !== $value) {
            throw new CastException($message);
        }

        return $date;
    }

    /**
     * @return array{type: string, format: string, example: string}
     */
    #[\Override]
    public function openApiSchema(): array
    {
        return ['type' => 'string', 'format' => 'date', 'example' => $this->example()];
    }

    private function example(): string
    {
        return new \DateTimeImmutable('2026-12-24T00:00:00+00:00')->format($this->format);
    }
}
