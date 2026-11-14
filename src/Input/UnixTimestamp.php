<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Seconds since the Unix epoch, as an integer or a base-10 string, into a DateTimeImmutable.
 */
final readonly class UnixTimestamp implements CasterInterface, OpenApiCasterInterface
{
    #[\Override]
    public function cast(mixed $value): \DateTimeImmutable
    {
        try {
            $seconds = ScalarCoercer::toInt($value);
        } catch (ConversionFailed) {
            throw new CastException('Unix timestamp must be an integer number of seconds');
        }

        return new \DateTimeImmutable('@' . $seconds);
    }

    /**
     * @return array{type: string, example: int}
     */
    #[\Override]
    public function openApiSchema(): array
    {
        return ['type' => 'integer', 'example' => 1798185600];
    }
}
