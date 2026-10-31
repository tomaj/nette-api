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
     * @throws \Exception when Redis is not available, the request then passes; a \LogicException or an \Error is a bug and answers 500
     */
    public function evaluate(string $script, array $keys, array $arguments): mixed;
}
