<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Tomaj\NetteApi\RateLimit\Redis\PredisClient;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

final class PredisStorageTest extends RedisTestCase
{
    #[\Override]
    protected function client(): Client
    {
        $client = new Client(['host' => self::host(), 'port' => self::port(), 'timeout' => 1.0]);
        try {
            $client->connect();
        } catch (ConnectionException $e) {
            $this->markTestSkipped('Redis is not reachable: ' . $e->getMessage());
        }
        return $client;
    }

    public function testWrappedClientWorksToo(): void
    {
        $client = $this->client();
        $this->assertInstanceOf(Client::class, $client);
        $storage = new RedisStorage(new PredisClient($client), $this->prefix);
        $this->assertSame(2, $storage->consume(new FixedWindow(), 'fixed', 3, 60, 1, 0.0)->remaining);
    }

    public function testScriptErrorIsReportedWithoutExceptions(): void
    {
        $client = new PredisClient(new Client(['host' => self::host(), 'port' => self::port(), 'timeout' => 1.0], ['exceptions' => false]));
        try {
            $client->evaluate('return redis.error_reply("NOPE")', [$this->prefix . 'x'], []);
            $this->fail('an error expected');
        } catch (\RuntimeException $e) {
            $this->assertMatchesRegularExpression('~^Redis EVAL failed: .*NOPE~i', $e->getMessage());
        }
    }

    public function testClientOptionsDoNotChangeTheCounters(): void
    {
        $storage = new RedisStorage(new Client(['host' => self::host(), 'port' => self::port(), 'timeout' => 1.0], ['prefix' => $this->prefix]), 'own:');
        $this->assertSame([2, 1, 0], array_map(fn (): int => $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->remaining, [1, 2, 3]));
    }
}
