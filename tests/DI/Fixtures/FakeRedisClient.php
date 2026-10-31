<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface;

final class FakeRedisClient implements RedisClientInterface
{
    #[\Override]
    public function evaluate(string $script, array $keys, array $arguments): mixed
    {
        return [1, 0, 1, -1];
    }
}
