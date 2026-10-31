<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use PHPUnit\Framework\Assert;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use Tomaj\NetteApi\RateLimit\Storage\KeyValueStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * Sends seeded random requests to a storage and checks the invariants of the algorithm:
 * - never more than the limit admitted in a window (fixed and sliding window: the window of the latest clock),
 *   never more than limit + rate * time in any period (token bucket)
 * - a request repeated after Retry-After passes, remaining requests of cost 1 pass and the next one does not
 * - the decisions are the same as of a storage that never forgets a value, so no TTL is shorter than needed
 * With skew, some requests come from a server whose clock is up to a half of the interval behind.
 */
final class InvariantChecker
{
    /** @var array<int, int> accepted cost per window */
    private array $windows = [];

    /** @var list<array{float, int}> accepted [time, cost] of the token bucket */
    private array $accepted = [];

    private float $latest = 0;

    private readonly KeyValueStorage $reference;

    private readonly string $key;

    public function __construct(
        private readonly Assert $assert,
        private readonly RateLimitStorageInterface $storage,
        private readonly RateLimitAlgorithmInterface $algorithm,
        private readonly int $limit,
        private readonly int $interval,
        private readonly bool $skew,
    ) {
        $this->reference = new class extends KeyValueStorage {
            /** @var array<string, mixed> */
            private array $values = [];

            protected function load(string $key, float $now): mixed
            {
                return $this->values[$key] ?? null;
            }

            protected function save(string $key, mixed $value, int $ttl, float $now): void
            {
                $this->values[$key] = $value;
            }
        };
        $this->key = 'inv' . bin2hex(random_bytes(4));
    }

    public function run(int $seed, int $requests): void
    {
        mt_srand($seed);
        $now = 1_000_000.0 + mt_rand(0, $this->interval * 1000) / 1000;
        try {
            for ($i = 0; $i < $requests; $i++) {
                // mostly bursts, sometimes a pause longer than the interval
                $now += mt_rand(0, 9) === 0 ? mt_rand(0, $this->interval * 2500) / 1000 : mt_rand(0, $this->interval * 100) / 1000;
                $at = $this->skew && mt_rand(0, 3) === 0 ? $now - mt_rand(0, $this->interval * 500) / 1000 : $now;
                $cost = mt_rand(1, min($this->limit, 3));
                $result = $this->consume($at, $cost, "request $i");
                if (!$result->accepted && mt_rand(0, 1) === 0) {
                    $this->assert->assertNotNull($result->retryAfter, "request $i rejected without Retry-After");
                    $now = max($now, $at + $result->retryAfter);
                    $this->assert->assertTrue($this->consume($at + $result->retryAfter, $cost, "retry of request $i")->accepted, "request $i at $at: waiting Retry-After {$result->retryAfter} is not enough");
                } elseif ($result->accepted && $result->remaining > 0 && $result->remaining <= 20 && mt_rand(0, 4) === 0 && $at === $now) {
                    for ($j = 0; $j < $result->remaining; $j++) {
                        $this->assert->assertTrue($this->consume($now, 1, "remaining of request $i")->accepted, "request $i at $now: remaining {$result->remaining}, but only $j more passed");
                    }
                    $this->assert->assertFalse($this->consume($now, 1, "over remaining of request $i")->accepted, "request $i at $now: remaining {$result->remaining}, but one more passed");
                }
            }
        } finally {
            mt_srand();
        }
    }

    private function consume(float $at, int $cost, string $what): StorageResult
    {
        // a clock gives microseconds
        $at = round($at, 6);
        $expected = $this->reference->consume($this->algorithm, $this->key, $this->limit, $this->interval, $cost, $at);
        $result = $this->storage->consume($this->algorithm, $this->key, $this->limit, $this->interval, $cost, $at);
        $this->assert->assertEquals($expected, $result, sprintf('%s at %.3F, cost %d: differs from a storage that never forgets (latest time %.3F)', $what, $at, $cost, $this->latest));
        $this->assert->assertGreaterThanOrEqual(0, $result->remaining);
        $this->assert->assertLessThanOrEqual($this->limit, $result->remaining);
        $this->assert->assertGreaterThanOrEqual(0, $result->resetAfter);
        $this->assert->assertSame($result->accepted, $result->retryAfter === null, "$what: Retry-After only on a rejection");
        if ($result->retryAfter !== null) {
            $this->assert->assertGreaterThan(0, $result->retryAfter, "$what: Retry-After must be positive");
        }
        $this->latest = max($this->latest, $at);
        if (!$result->accepted) {
            return $result;
        }
        if ($this->algorithm instanceof TokenBucket) {
            $this->accepted[] = [$at, $cost];
            $this->checkBucket();
        } else {
            $window = intdiv((int) $this->latest, $this->interval);
            $this->windows[$window] = ($this->windows[$window] ?? 0) + $cost;
            $this->assert->assertLessThanOrEqual($this->limit, $this->windows[$window], sprintf('%s at %.3F: more than the limit admitted in the window %d', $what, $at, $window));
        }

        return $result;
    }

    private function checkBucket(): void
    {
        // in every period from an accepted request to the last one: at most the full bucket plus the refill
        // (with skew the time of the bucket is the latest one, so the period is measured from the max time)
        $count = 0;
        $last = $this->accepted[array_key_last($this->accepted)][0];
        $end = max($last, $this->latest);
        $over = null;
        foreach (array_reverse(array_slice($this->accepted, -200)) as [$time, $cost]) {
            $count += $cost;
            if ($count > $this->limit + max(0, $end - $time) * $this->limit / $this->interval + 1e-6) {
                $over = sprintf('%d units admitted from %.3F to %.3F', $count, $time, $end);
                break;
            }
        }
        $this->assert->assertNull($over, 'more than the full bucket plus the refill');
    }
}
