<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

/**
 * Counters in a PHP array, they live only for one PHP process (one request in PHP-FPM).
 * For tests and long-running workers; in a web application use NetteCacheStorage or RedisStorage.
 */
final class InMemoryStorage extends KeyValueStorage
{
    /** @var array<string, array{mixed, float}> value and expiry */
    private array $values = [];

    /** the number of values at which the expired ones are removed, so a long-running worker does not grow without a bound */
    private int $pruneAt = 1000;

    #[\Override]
    protected function load(string $key, float $now): mixed
    {
        $entry = $this->values[$key] ?? null;
        if ($entry === null || $entry[1] <= $now) {
            unset($this->values[$key]);
            return null;
        }

        return $entry[0];
    }

    #[\Override]
    protected function save(string $key, mixed $value, int $ttl, float $now): void
    {
        $this->values[$key] = [$value, $now + $ttl];
        if (count($this->values) >= $this->pruneAt) {
            $this->values = array_filter($this->values, fn(array $entry): bool => $entry[1] > $now);
            $this->pruneAt = max(1000, 2 * count($this->values));
        }
    }

    /**
     * Forget all counters, for example between tests
     */
    public function clear(): void
    {
        $this->values = [];
    }
}
