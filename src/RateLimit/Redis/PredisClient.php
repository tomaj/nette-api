<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Redis;

use Predis\ClientInterface;
use Predis\Response\ErrorInterface;

/**
 * RedisClientInterface for predis/predis, a Redis client in plain PHP without an extension
 */
final readonly class PredisClient implements RedisClientInterface
{
    public function __construct(private ClientInterface $redis)
    {
    }

    #[\Override]
    public function evaluate(string $script, array $keys, array $arguments): mixed
    {
        $result = $this->redis->eval($script, count($keys), ...$keys, ...$arguments);
        // a client with the option exceptions: false returns the error instead of throwing it
        if ($result instanceof ErrorInterface) {
            throw new \RuntimeException('Redis EVAL failed: ' . $result->getMessage());
        }

        return $result;
    }
}
