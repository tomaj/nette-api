<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\Test\RateLimit\ContextFactory;
use Tracy\ILogger;

/**
 * Exact values, saved states and TTLs of the built-in algorithms in a key-value storage, with times that are not whole seconds
 */
final class KeyValueStorageTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private static function storage(array $values = []): RecordingStorage
    {
        $storage = new RecordingStorage();
        $storage->values = $values;
        return $storage;
    }

    /**
     * @return array{bool, int, int, int|null}
     */
    private static function values(StorageResult $result): array
    {
        return [$result->accepted, $result->remaining, $result->resetAfter, $result->retryAfter];
    }

    public function testFixedWindow(): void
    {
        $storage = self::storage();
        $this->assertSame([true, 2, 20, null], self::values($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.7)));
        $this->assertSame([['k', [960, 1], 21]], $storage->saved);

        $storage = self::storage(['k' => [960, 5]]);
        $this->assertSame([false, 0, 20, 20], self::values($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.7)), 'more than the limit after a config change');
        $this->assertSame([], $storage->saved);
    }

    public function testSlidingWindowAccepted(): void
    {
        $storage = self::storage(['k' => [900, 0, 3]]);
        // weight of the previous window 1 - 40.7 / 60 = 0.32166..., estimate 0.965 + 0 + 1
        $this->assertSame([true, 2, 20, null], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 1, 1000.7)));
        $this->assertSame([['k', [960, 3, 1], 81]], $storage->saved);
    }

    public function testSlidingWindowWaitsForThePreviousWindow(): void
    {
        // estimate 6 * 0.32166 + 1 = 2.93, + 2 > 4; the current window has room, 0.93 of the previous must slide out
        $storage = self::storage(['k' => [960, 6, 1]]);
        $this->assertSame([false, 1, 20, 10], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 2, 1000.7)));
        $this->assertSame([], $storage->saved);

        // previous 1: 0.32 + 3 + 1 > 4 by 0.32, which is 19.3 seconds of the previous window
        $storage = self::storage(['k' => [960, 1, 3]]);
        $this->assertSame([false, 0, 20, 20], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 1, 1000.7)));
    }

    public function testSlidingWindowWaitsForTheCurrentWindow(): void
    {
        // the current window is full, after 19.3 s it is the previous one and 1/4 of it must slide out: 19.3 + 15
        $storage = self::storage(['k' => [960, 0, 4]]);
        $this->assertSame([false, 0, 20, 35], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 1, 1000.7)));

        // one over the limit: 19.3 + 60 * 2 / 4
        $storage = self::storage(['k' => [960, 2, 4]]);
        $this->assertSame([false, 0, 20, 50], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 2, 1000.7)));

        // a cost over the limit in an empty window: 19.3 + 60 * 1
        $storage = self::storage();
        $this->assertSame([false, 4, 20, 80], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 5, 1000.7)));
    }

    public function testTokenBucket(): void
    {
        $storage = self::storage();
        $this->assertSame([true, 1, 5, null], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 1, 100.0)));
        $this->assertSame([['k', [1.0, 100.0], 11]], $storage->saved);
        $this->assertSame([false, 1, 5, 5], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 2, 100.0)), 'the saved tokens are used');

        // 0.3 tokens + 2.1 s * 0.2 = 0.72
        $storage = self::storage(['k' => [0.3, 100.0]]);
        $this->assertSame([false, 0, 7, 2], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 1, 102.1)));

        $storage = self::storage(['k' => [1.5, 100.0]]);
        $this->assertSame([false, 1, 4, 4], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 2, 99.0)), 'a clock going back adds no tokens, and the times are by its own clock');
    }

    public function testTokenBucketIgnoresAnUnknownValue(): void
    {
        foreach ([[1.0], [0.0, 'x'], ['0', 1.0], [0, 1.0], [0.0, 1], 'x'] as $value) {
            $storage = self::storage(['k' => $value]);
            $this->assertSame([true, 1, 5, null], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 1, 100.0)), var_export($value, true));
        }
        $storage = self::storage(['k' => [0.0, 99.0]]);
        $this->assertSame([false, 0, 10, 5], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 1, 99.0)));
    }

    public function testWindowsIgnoreAnUnknownOrOldState(): void
    {
        foreach ([[900, 5], [960, '5'], [960], 'x', ['2000', 5], [960, 5, 0]] as $value) {
            $storage = self::storage(['k' => $value]);
            $this->assertSame([true, 2, 20, null], self::values($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.7)), var_export($value, true));
        }
        foreach ([[960, 4, '4'], [960, '4', 4], [840, 4, 4], [960], 'x', ['2000', 4, 4]] as $value) {
            $storage = self::storage(['k' => $value]);
            $this->assertSame([true, 3, 20, null], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 1, 1000.7)), var_export($value, true));
            $this->assertSame([['k', [960, 0, 1], 81]], $storage->saved);
        }
        $storage = self::storage(['k' => [900, 9, 2]]);
        $this->assertSame([true, 2, 20, null], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 1, 1000.7)), 'the current count of the last window becomes the previous one');
        $this->assertSame([['k', [960, 2, 1], 81]], $storage->saved);
    }

    public function testWindowsKeepTheNewerWindowOfServersWithSkewedClocks(): void
    {
        // the times are by the clock of the server behind: the newer window ends at 1080, 79.3 s later
        $storage = self::storage(['k' => [1020, 2]]);
        $this->assertSame([true, 0, 80, null], self::values($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.7)), 'a clock behind counts in the newer window');
        $this->assertSame([['k', [1020, 3], 81]], $storage->saved);
        $storage = self::storage(['k' => [1020, 3, 2]]);
        $this->assertSame([true, 0, 80, null], self::values($storage->consume(new SlidingWindow(), 'k', 6, 60, 1, 1000.7)), 'a clock behind counts in the newer window');
        $this->assertSame([['k', [1020, 3, 3], 141]], $storage->saved);
        $storage = self::storage(['k' => [1020, 3, 3]]);
        $this->assertSame([false, 0, 80, 40], self::values($storage->consume(new SlidingWindow(), 'k', 6, 60, 1, 1000.7)), 'Retry-After by the clock behind: at 1040.7 the previous window weighs 1.97');
        $storage = self::storage(['k' => [1020, '2']]);
        $this->assertSame([true, 2, 80, null], self::values($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.7)), 'an unknown count starts the newer window again');
        $storage = self::storage(['k' => [1020, '3', 2]]);
        $this->assertSame([true, 5, 80, null], self::values($storage->consume(new SlidingWindow(), 'k', 6, 60, 1, 1000.7)), 'an unknown count starts the newer window again');
        $storage = self::storage(['k' => [1030, 2]]);
        $this->assertSame([true, 2, 80, null], self::values($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.7)), 'a start out of the windows (another interval) is aligned and its count not used');
        $this->assertSame([['k', [1020, 1], 81]], $storage->saved);
        $storage = self::storage(['k' => [1030, 2, 2]]);
        $this->assertSame([true, 5, 80, null], self::values($storage->consume(new SlidingWindow(), 'k', 6, 60, 1, 1000.7)));
        $this->assertSame([['k', [1020, 0, 1], 141]], $storage->saved);
    }

    public function testCustomAlgorithmGetsItsState(): void
    {
        $storage = self::storage(['k' => 'old']);
        $algorithm = new LastRequestAlgorithm();
        $this->assertSame([true, 0, 0, null], self::values($storage->consume($algorithm, 'k', 3, 60, 2, 5.5)));
        $this->assertSame([['old', 3, 60, 2, 5.5]], $algorithm->calls);
        $this->assertSame([['k', 'state 5.5', 1]], $storage->saved);
        $algorithm->keep = true;
        $storage->consume($algorithm, 'k', 3, 60, 2, 6.0);
        $this->assertCount(1, $storage->saved, 'a null state is not saved');
    }

    public function testSlidingWindowRemainingIsRoundedDown(): void
    {
        // 4 - 1 * 0.32166 - 1 = 2.68
        $storage = self::storage(['k' => [960, 1, 0]]);
        $this->assertSame([true, 2, 20, null], self::values($storage->consume(new SlidingWindow(), 'k', 4, 60, 1, 1000.7)));
    }

    public function testTokenBucketNeedsAnArrayOfTwoFloats(): void
    {
        foreach ([[0, 100.0], new \ArrayObject([0.0, 100.0])] as $value) {
            $storage = self::storage(['k' => $value]);
            $this->assertSame([true, 1, 5, null], self::values($storage->consume(new TokenBucket(), 'k', 2, 10, 1, 100.0)), get_debug_type($value));
        }
    }

    public function testErrorsOfAnOwnStorageLetTheRequestPass(): void
    {
        $storage = new FailingStorage(new \PDOException('server has gone away'));
        try {
            $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.0);
            $this->fail('a storage exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame("The rate limit counter 'k' cannot be read from the storage " . FailingStorage::class . ': server has gone away', $e->getMessage());
            $this->assertSame(0, $e->getCode());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }

        $storage = new FailingStorage(null, new \RuntimeException('read only'));
        try {
            $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.0);
            $this->fail('a storage exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame("The rate limit counter 'k' cannot be saved to the storage " . FailingStorage::class . ': read only', $e->getMessage());
            $this->assertSame(0, $e->getCode());
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }

        $own = new RateLimitStorageException('own message');
        foreach ([new FailingStorage($own), new FailingStorage(null, $own)] as $storage) {
            try {
                $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 1000.0);
                $this->fail('a storage exception expected');
            } catch (RateLimitStorageException $e) {
                $this->assertSame($own, $e, 'a RateLimitStorageException is not wrapped again');
            }
        }

        $limiter = new FixedWindowLimiter('p', 3, 60, new IpKey(), new FailingStorage(new \PDOException('server has gone away')), null, $logger = new class implements ILogger {
            /** @var list<mixed> */
            public array $logs = [];

            public function log(mixed $value, string $level = self::INFO): void
            {
                $this->logs[] = $value;
            }
        });
        $this->assertSame([], $limiter->consume(ContextFactory::create()), 'the request passes (fail-open)');
        $this->assertCount(1, $logger->logs);
    }

    public function testStateNeedsTtl(): void
    {
        $result = new StorageResult(true, 0, 0, null);
        $this->assertSame(0, new AlgorithmStep($result)->ttl);
        $this->assertSame(1, new AlgorithmStep($result, 'x', 1)->ttl);
        foreach ([[], [0], [-1]] as $ttl) {
            try {
                new AlgorithmStep($result, 'x', ...$ttl);
                $this->fail('a ttl is needed');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('A rate limit algorithm returned a state with the ttl ' . ($ttl[0] ?? 0) . ', but the storage needs at least 1 second to keep it. Return the seconds after which the state is not needed, for example new AlgorithmStep($result, $state, $interval + 1).', $e->getMessage());
            }
        }
    }
}
