<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Storage\KeyValueStorage;

/**
 * An own storage whose backend fails (a database outage, ...) with an exception of its own type
 */
final class FailingStorage extends KeyValueStorage
{
    public function __construct(private readonly ?\Throwable $loadError, private readonly ?\Throwable $saveError = null)
    {
    }

    #[\Override]
    protected function load(string $key, float $now): mixed
    {
        if ($this->loadError !== null) {
            throw $this->loadError;
        }
        return null;
    }

    #[\Override]
    protected function save(string $key, mixed $value, int $ttl, float $now): void
    {
        if ($this->saveError !== null) {
            throw $this->saveError;
        }
    }
}
