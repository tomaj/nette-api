<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Redis;

/**
 * The one Redis command RedisStorage needs. Implementations: PhpRedisClient (ext-redis) and PredisClient (predis/predis).
 */
interface RedisClientInterface
{
    /**
     * Runs a Lua script with EVAL
     *
     * @param list<string> $keys
     * @param list<string> $arguments
     * @throws \Throwable when Redis is not available
     */
    public function evaluate(string $script, array $keys, array $arguments): mixed;
}
