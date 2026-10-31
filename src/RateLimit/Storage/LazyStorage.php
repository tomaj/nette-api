<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * Creates the storage on the first request that has a rate limit, not together with the API presenter.
 *
 * A storage that cannot be created (Redis is down and the client connects in its setup) is a RateLimitStorageException,
 * so the limiter lets the request through like with any other storage outage, instead of the whole API failing.
 * The failure is remembered for RETRY seconds, so a chain of limiters does not wait for the connection timeout again.
 * A \LogicException (a bug, a wrong configuration) is not hidden.
 */
final class LazyStorage implements RateLimitStorageInterface
{
    public const float RETRY = 1.0;

    private ?RateLimitStorageInterface $storage = null;

    private ?RateLimitStorageException $failure = null;

    private float $failedAt = 0.0;

    /**
     * @param \Closure(): RateLimitStorageInterface $factory
     */
    public function __construct(private readonly \Closure $factory)
    {
    }

    #[\Override]
    public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult
    {
        return $this->getStorage()->consume($algorithm, $key, $limit, $interval, $cost, $now);
    }

    /**
     * @throws RateLimitStorageException when the storage cannot be created
     */
    public function getStorage(): RateLimitStorageInterface
    {
        if ($this->storage !== null) {
            return $this->storage;
        }
        if ($this->failure !== null && $this->waitsForRetry()) {
            throw $this->failure;
        }
        try {
            return $this->storage = ($this->factory)();
        } catch (RateLimitStorageException $e) {
            $failure = $e;
        } catch (\LogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            $failure = new RateLimitStorageException(sprintf('The rate limit storage cannot be created: %s', $e->getMessage()), previous: $e);
        }
        $this->failedAt = microtime(true);
        throw $this->failure = $failure;
    }

    /**
     * The exact boundary of a float time cannot be tested, < and <= behave the same.
     * @infection-ignore-all
     */
    private function waitsForRetry(): bool
    {
        return microtime(true) - $this->failedAt < self::RETRY;
    }
}
