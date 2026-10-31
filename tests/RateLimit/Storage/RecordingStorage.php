<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Storage\KeyValueStorage;

final class RecordingStorage extends KeyValueStorage
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var list<array{string, mixed, int}> */
    public array $saved = [];

    #[\Override]
    protected function load(string $key, float $now): mixed
    {
        return $this->values[$key] ?? null;
    }

    #[\Override]
    protected function save(string $key, mixed $value, int $ttl, float $now): void
    {
        $this->values[$key] = $value;
        $this->saved[] = [$key, $value, $ttl];
    }
}
