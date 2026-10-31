<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * A storage that only loads and saves one value per key; any RateLimitAlgorithmInterface works with it.
 * Implement load() and save() to keep the counters somewhere else (Memcached, a database, ...). The value is read and written in two steps,
 * so two parallel requests can both read the same counter and both pass: the limit is not exact under load.
 * Use RedisStorage when the limit must hold exactly.
 *
 * Any exception of load() or save() becomes RateLimitStorageException, so the request passes with a warning (fail-open).
 */
abstract class KeyValueStorage implements RateLimitStorageInterface
{
    abstract protected function load(string $key, float $now): mixed;

    /**
     * @param int $ttl seconds after which the value is not needed
     */
    abstract protected function save(string $key, mixed $value, int $ttl, float $now): void;

    #[\Override]
    public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult
    {
        try {
            $state = $this->load($key, $now);
        } catch (\Throwable $e) {
            throw self::failure('read from', $key, $e);
        }
        $step = $algorithm->step($state, $limit, $interval, $cost, $now);
        if ($step->state !== null) {
            try {
                $this->save($key, $step->state, $step->ttl, $now);
            } catch (\Throwable $e) {
                throw self::failure('saved to', $key, $e);
            }
        }

        return $step->result;
    }

    /**
     * Any error of load() or save() (a database or Memcached outage, ...) lets the request pass with a warning, see StorageRateLimiter
     */
    private static function failure(string $action, string $key, \Throwable $e): RateLimitStorageException
    {
        return $e instanceof RateLimitStorageException
            ? $e
            : new RateLimitStorageException(sprintf("The rate limit counter '%s' cannot be %s the storage %s: %s", $key, $action, static::class, $e->getMessage()), 0, $e);
    }
}
