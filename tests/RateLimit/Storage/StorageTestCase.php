<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\Test\RateLimit\Algorithm\MinimumGap;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

/**
 * The same expectations for every storage, so the Lua scripts and the PHP algorithms count the same way
 */
abstract class StorageTestCase extends TestCase
{
    abstract protected function storage(): RateLimitStorageInterface;

    private function assertResult(bool $accepted, int $remaining, int $resetAfter, ?int $retryAfter, StorageResult $result, string $message = ''): void
    {
        $this->assertSame(
            ['accepted' => $accepted, 'remaining' => $remaining, 'resetAfter' => $resetAfter, 'retryAfter' => $retryAfter],
            ['accepted' => $result->accepted, 'remaining' => $result->remaining, 'resetAfter' => $result->resetAfter, 'retryAfter' => $result->retryAfter],
            $message,
        );
    }

    public function testFixedWindow(): void
    {
        $storage = $this->storage();
        $this->assertResult(true, 2, 20, null, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1000.5));
        $this->assertResult(true, 1, 20, null, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1000.5));
        $this->assertResult(true, 0, 1, null, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1019.5));
        $this->assertResult(false, 0, 1, 1, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1019.5), 'the window is full');
        $this->assertResult(true, 2, 60, null, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1020.0), 'a new window starts empty');
        $this->assertResult(false, 2, 60, 60, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 3, 1020.0), 'a cost over the remaining is rejected and not counted');
        $this->assertResult(true, 0, 60, null, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 2, 1020.0));
        $this->assertResult(true, 2, 20, null, $storage->consume(new FixedWindow(), 'other', 3, 60, 1, 1000.5), 'keys have own counters');
    }

    public function testSlidingWindow(): void
    {
        $storage = $this->storage();
        foreach ([3, 2, 1] as $remaining) {
            $this->assertResult(true, $remaining, 60, null, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 960.0));
        }
        $this->assertResult(true, 0, 75, null, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 960.0), 'nothing is left, t is the wait for the next unit, not the next window');
        $this->assertResult(false, 0, 75, 75, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 960.0), 'the current window is full, it must slide out to 3 of 4');
        $this->assertResult(false, 0, 1, 1, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 1034.0), 'the previous window still weighs 3.07');
        $this->assertResult(true, 0, 15, null, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 1035.0));
        $this->assertResult(false, 0, 15, 15, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 1035.0), 'the previous window counts too much');
        $this->assertResult(true, 0, 15, null, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 1050.0), 'half of the previous window slid out');
        $this->assertResult(true, 3, 60, null, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 1, 1140.0), 'old windows do not count');
        $this->assertResult(false, 3, 60, 120, $storage->consume(new SlidingWindow(), 'sliding', 4, 60, 4, 1140.0), 'the cost needs the whole window to slide out');
    }

    public function testTokenBucket(): void
    {
        $storage = $this->storage();
        $this->assertResult(true, 1, 5, null, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 1, 100.0));
        $this->assertResult(true, 0, 10, null, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 1, 100.0));
        $this->assertResult(false, 0, 10, 5, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 1, 100.0), 'the bucket is empty');
        $this->assertResult(false, 0, 8, 3, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 1, 102.0), 'a part of a token is refilled');
        $this->assertResult(true, 0, 10, null, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 1, 105.0), 'one token is refilled');
        $this->assertResult(false, 0, 10, 10, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 2, 105.0));
        $this->assertResult(true, 0, 10, null, $storage->consume(new TokenBucket(), 'bucket', 2, 10, 2, 200.0), 'the bucket is full after a long pause, not over full');
        $this->assertResult(true, 1, 5, null, $storage->consume(new TokenBucket(), 'other', 2, 10, 1, 100.0), 'keys have own buckets');
    }

    public function testTokenBucketKeepsTheLatestTimeOfServersWithSkewedClocks(): void
    {
        $storage = $this->storage();
        $this->assertResult(true, 0, 10, null, $storage->consume(new TokenBucket(), 'skew', 2, 10, 2, 100.0));
        $this->assertResult(false, 0, 15, 10, $storage->consume(new TokenBucket(), 'skew', 2, 10, 1, 95.0), 'a server 5 seconds behind refills nothing, it measures the times by its own clock');
        $this->assertResult(false, 0, 10, 5, $storage->consume(new TokenBucket(), 'skew', 2, 10, 1, 100.0), 'and does not move the time back, so nothing is refilled twice');
        $this->assertResult(true, 0, 10, null, $storage->consume(new TokenBucket(), 'skew', 2, 10, 1, 105.0));
    }

    public function testWaitingTheResetTimeOfAFullSlidingWindowPasses(): void
    {
        $storage = $this->storage();
        $now = 1230.0; // 30 seconds into a window
        for ($i = 0; $i < 9; $i++) {
            $storage->consume(new SlidingWindow(), 'full', 10, 60, 1, $now);
        }
        $last = $storage->consume(new SlidingWindow(), 'full', 10, 60, 1, $now);
        $this->assertResult(true, 0, 36, null, $last);
        $this->assertResult(false, 0, 36, 36, $storage->consume(new SlidingWindow(), 'full', 10, 60, 1, $now));
        $this->assertTrue($storage->consume(new SlidingWindow(), 'full', 10, 60, 1, $now + $last->resetAfter)->accepted, 'a client waiting t is not rejected again');
    }

    public function testWindowsKeepTheNewerWindowOfServersWithSkewedClocks(): void
    {
        $storage = $this->storage();
        $this->assertResult(true, 1, 60, null, $storage->consume(new FixedWindow(), 'skew', 3, 60, 2, 1020.5));
        $this->assertResult(true, 0, 62, null, $storage->consume(new FixedWindow(), 'skew', 3, 60, 1, 1018.0), 'a server 2.5 seconds behind counts in the newer window, not in the older one');
        $this->assertResult(false, 0, 62, 62, $storage->consume(new FixedWindow(), 'skew', 3, 60, 1, 1018.0), 'so it does not add the limit of the older window');

        $this->assertResult(true, 1, 60, null, $storage->consume(new SlidingWindow(), 'skew2', 4, 60, 3, 1020.0));
        $this->assertResult(true, 0, 77, null, $storage->consume(new SlidingWindow(), 'skew2', 4, 60, 1, 1018.0), 'a server 2 seconds behind counts in the newer window');
        $this->assertResult(false, 0, 77, 77, $storage->consume(new SlidingWindow(), 'skew2', 4, 60, 1, 1018.0), 'and measures Retry-After by its own clock');
    }

    public function testCustomAlgorithm(): void
    {
        $storage = $this->storage();
        $this->assertResult(true, 0, 5, null, $storage->consume(new MinimumGap(), 'gap', 2, 10, 1, 100.0));
        $this->assertResult(false, 0, 2, 2, $storage->consume(new MinimumGap(), 'gap', 2, 10, 1, 103.0), 'only 3 of 5 seconds passed');
        $this->assertResult(true, 0, 5, null, $storage->consume(new MinimumGap(), 'gap', 2, 10, 1, 105.0));
        $this->assertResult(true, 0, 5, null, $storage->consume(new MinimumGap(), 'other', 2, 10, 1, 105.0), 'keys have own state');
    }

    /**
     * @return iterable<string, array{RateLimitAlgorithmInterface, int, int}>
     */
    public static function invariantCases(): iterable
    {
        foreach (['fixed window' => new FixedWindow(), 'sliding window' => new SlidingWindow(), 'token bucket' => new TokenBucket()] as $name => $algorithm) {
            foreach ([[5, 30], [1, 1], [7, 3], [100, 3600]] as [$limit, $interval]) {
                yield sprintf('%s %d/%ds', $name, $limit, $interval) => [$algorithm, $limit, $interval];
            }
        }
    }

    /**
     * False when servers with a clock behind the others may count in an older window (Redis keeps a key per window)
     */
    protected function windowsSurviveClockSkew(): bool
    {
        return true;
    }

    /**
     * Random requests (random times, costs, keys, clock skew) keep the invariants of the algorithm:
     * never more than the limit admitted, an honest Retry-After and remaining, the same decisions as a storage that never forgets
     */
    #[DataProvider('invariantCases')]
    public function testInvariantsHoldForRandomRequests(RateLimitAlgorithmInterface $algorithm, int $limit, int $interval): void
    {
        foreach ([false, true] as $skew) {
            if ($skew && !$algorithm instanceof TokenBucket && !$this->windowsSurviveClockSkew()) {
                continue;
            }
            new InvariantChecker($this, $this->storage(), $algorithm, $limit, $interval, $skew)->run(crc32(static::class . $limit . $interval . (int) $skew), 300);
        }
    }
}
