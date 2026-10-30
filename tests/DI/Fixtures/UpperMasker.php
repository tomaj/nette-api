<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\Logger\LogValueMaskerInterface;

final readonly class UpperMasker implements LogValueMaskerInterface
{
    public function __construct(private string $format = '%s')
    {
    }

    #[\Override]
    public function mask(string $value): ?string
    {
        return sprintf($this->format, strtoupper($value));
    }
}
