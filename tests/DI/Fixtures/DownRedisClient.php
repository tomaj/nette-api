<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface;

/**
 * A Redis client that connects in its setup, like Redis() with connect(), while Redis is down
 */
final class DownRedisClient implements RedisClientInterface
{
    public static int $connections = 0;

    public function connect(): never
    {
        self::$connections++;
        throw new \RuntimeException('Connection refused');
    }

    #[\Override]
    public function evaluate(string $script, array $keys, array $arguments): mixed
    {
        throw new \LogicException('never connected');
    }
}
