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
        if (!is_string($value) || $value === '' || str_contains($value, "\0")) {
            throw new CastException($message);
        }

        $date = \DateTimeImmutable::createFromFormat(sprintf('!%s', $this->format), $value);
        if ($date === false || $date->format($this->format) !== $value) {
            throw new CastException($message);
        }

        return $date;
    }

    /**
     * @return array{type: string, format?: string, example: string}
     */
    #[\Override]
    public function openApiSchema(): array
    {
        // OpenAPI `date` is the RFC 3339 full-date, so other formats are a plain string with an example
        if ($this->format === 'Y-m-d') {
            return ['type' => 'string', 'format' => 'date', 'example' => $this->example()];
        }

        return ['type' => 'string', 'example' => $this->example()];
    }

    private function example(): string
    {
        return new \DateTimeImmutable('2026-12-24T00:00:00+00:00')->format($this->format);
    }
}
