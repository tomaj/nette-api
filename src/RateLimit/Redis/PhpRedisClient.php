<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Redis;

/**
 * RedisClientInterface for the redis PHP extension (\Redis or \RedisCluster)
 */
final readonly class PhpRedisClient implements RedisClientInterface
{
    public function __construct(private \Redis|\RedisCluster $redis)
    {
    }

    #[\Override]
    public function evaluate(string $script, array $keys, array $arguments): mixed
    {
        $result = $this->redis->eval($script, array_merge($keys, $arguments), count($keys));
        if ($result === false && ($error = $this->redis->getLastError()) !== null) {
            $this->redis->clearLastError();
            throw new \RuntimeException('Redis EVAL failed: ' . $error);
        }

        return $result;
    }
}
