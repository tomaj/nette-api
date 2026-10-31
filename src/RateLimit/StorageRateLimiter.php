<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

use Psr\Clock\ClockInterface;
use Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * The common part of the built-in limiters: the key decides the stage, the storage keeps the state and the algorithm decides.
 *
 * When the storage is not available (any exception except a \LogicException, which is a bug or a wrong configuration),
 * the request passes (fail-open) and a warning goes to the Tracy log (or to the given ILogger), so a Redis outage
 * does not take the API down.
 */
abstract class StorageRateLimiter implements RateLimiterInterface
{
    /** a longer key is hashed */
    public const int MAX_KEY_LENGTH = 200;

    /** the highest limit, the storages count in floating point numbers (Lua) */
    public const int MAX_LIMIT = 1_000_000_000;

    /** @var int<1, max> */
    private readonly int $limit;

    /** @var int<1, max> */
    private readonly int $interval;

    private readonly ClockInterface $clock;

    /**
     * @param string $policy the name sent in RateLimit-Policy and RateLimit headers and used in the storage key
     * @param int $limit how many requests the policy allows per interval
     * @param int|string $interval seconds or an interval like '1 minute', '30 seconds', '1 hour'
     */
    public function __construct(
        private readonly string $policy,
        int $limit,
        int|string $interval,
        private readonly RateLimitKeyInterface $key,
        private readonly RateLimitStorageInterface $storage,
        ?ClockInterface $clock = null,
        private readonly ?ILogger $logger = null,
    ) {
        if (preg_match('~^[A-Za-z0-9_.-]+$~D', $policy) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                "%s: the policy name '%s' is not valid, it is sent in the RateLimit-Policy header and used in the storage key. Use only letters, digits, '_', '-' and '.', for example 'perIp'.",
                static::class,
                $policy,
            ));
        }
        if ($limit < 1) {
            throw new \InvalidArgumentException(sprintf(
                "%s '%s': the limit %d is less than 1. Use the number of requests allowed per interval, for example 60; to turn the limit off, use rateLimit: false on the endpoint.",
                static::class,
                $policy,
                $limit,
            ));
        }
        if ($limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(sprintf(
                "%s '%s': the limit %d is more than %d. Use at most %d requests per interval, the storages count in floating point numbers and lose precision above it.",
                static::class,
                $policy,
                $limit,
                self::MAX_LIMIT,
                self::MAX_LIMIT,
            ));
        }
        $this->limit = $limit;
        $this->interval = Interval::toSeconds($interval, sprintf("%s '%s'", static::class, $policy));
        $this->clock = $clock ?? new SystemClock();
    }

    #[\Override]
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        if ($this->key->needsIdentity() !== ($context->stage === RateLimitStage::AfterAuthorization)) {
            return [];
        }
        if ($cost < 1 || $cost > $this->limit) {
            throw new \InvalidArgumentException(sprintf(
                "Rate limit '%s': the cost %d must be between 1 and the limit %d, a cost above the limit would never pass. Pass a cost from 1 up to the limit to consume(), or raise the limit of the policy.",
                $this->policy,
                $cost,
                $this->limit,
            ));
        }
        $key = $this->key->key($context);
        if ($key === null) {
            if ($this->key->needsIdentity() && $context->identity === null && $context->api->getAuthorization() instanceof AuthorizationIdentityInterface) {
                // for example a subclass that overrides authorized() without the parent, the limit would never apply
                $this->warn(sprintf(
                    "Rate limit '%s' is skipped, the authorization %s returned no identity after a successful authorized(). When it overrides authorized(), override getIdentity() too.",
                    $this->policy,
                    $context->api->getAuthorization()::class,
                ));
            }
            return [];
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            // an own key may come from the request (a header, ...), a long one is hashed to keep the storage small
            $key = 'sha256:' . hash('sha256', $key);
        }
        $now = (float) $this->clock->now()->format('U.u');
        try {
            $result = $this->storage->consume($this->algorithm(), $this->policy . ':' . $key, $this->limit, $this->interval, $cost, $now);
        } catch (\LogicException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->warn(sprintf("Rate limit '%s' is skipped, the request passed without a limit: %s", $this->policy, $e->getMessage()));
            return [];
        }

        return [new RateLimitResult($result->accepted, $this->limit, $result->remaining, $this->interval, $result->resetAfter, $result->retryAfter, $this->policy)];
    }

    public function getPolicy(): string
    {
        return $this->policy;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function getKey(): RateLimitKeyInterface
    {
        return $this->key;
    }

    abstract protected function algorithm(): RateLimitAlgorithmInterface;

    private function warn(string $message): void
    {
        try {
            $this->logger !== null ? $this->logger->log($message, ILogger::WARNING) : Debugger::log($message, ILogger::WARNING);
        } catch (\Throwable $logError) {
            // a logger without a log directory must not turn the fail-open into a 500
            error_log($message . ' (the logger failed: ' . $logError->getMessage() . ')');
        }
    }
}
