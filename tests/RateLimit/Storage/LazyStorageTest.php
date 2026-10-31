<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\LazyStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;

final class LazyStorageTest extends TestCase
{
    public function testStorageIsCreatedOnTheFirstUseOnce(): void
    {
        $created = 0;
        $inner = new InMemoryStorage();
        $storage = new LazyStorage(function () use (&$created, $inner): InMemoryStorage {
            $created++;
            return $inner;
        });
        $this->assertSame(0, $created);
        $this->assertSame(1, $storage->consume(new FixedWindow(), 'k', 2, 60, 1, 1000.0)->remaining);
        $this->assertSame(0, $storage->consume(new FixedWindow(), 'k', 2, 60, 1, 1000.0)->remaining);
        $this->assertFalse($storage->consume(new FixedWindow(), 'k', 2, 60, 1, 1000.0)->accepted, 'the counter is in the created storage');
        $this->assertSame($inner, $storage->getStorage());
        $this->assertSame(1, $created);
    }

    public function testStorageThatCannotBeCreatedFailsOpenAndIsRetried(): void
    {
        $attempts = 0;
        $error = new \RuntimeException('Connection refused', 111);
        $storage = new LazyStorage(function () use (&$attempts, &$error): InMemoryStorage {
            $attempts++;
            if ($error !== null) {
                throw $error;
            }
            return new InMemoryStorage();
        });
        try {
            $storage->consume(new FixedWindow(), 'k', 2, 60, 1, 1000.0);
            $this->fail('a storage exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame('The rate limit storage cannot be created: Connection refused', $e->getMessage());
            $this->assertSame(0, $e->getCode());
            $this->assertSame($error, $e->getPrevious());
            $first = $e;
        }
        try {
            $storage->getStorage();
            $this->fail('a storage exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame($first, $e, 'the failure is remembered');
        }
        $this->assertSame(1, $attempts, 'other limiters of the request do not wait for the connection again');

        $failedAt = new \ReflectionProperty(LazyStorage::class, 'failedAt');
        $this->assertEqualsWithDelta(microtime(true), $failedAt->getValue($storage), 0.5);
        $failedAt->setValue($storage, microtime(true) - LazyStorage::RETRY + 0.5);
        try {
            $storage->getStorage();
            $this->fail('still remembered');
        } catch (RateLimitStorageException) {
            $this->assertSame(1, $attempts);
        }
        $failedAt->setValue($storage, microtime(true) - LazyStorage::RETRY - 0.01);
        $error = new \RuntimeException('Still down');
        try {
            $storage->getStorage();
            $this->fail('a storage exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame('The rate limit storage cannot be created: Still down', $e->getMessage());
            $this->assertSame(2, $attempts, 'tried again after RETRY seconds');
        }
        $failedAt->setValue($storage, microtime(true) - LazyStorage::RETRY - 0.01);
        $error = null;
        $this->assertTrue($storage->consume(new FixedWindow(), 'k', 2, 60, 1, 1000.0)->accepted, 'it works when Redis is back');
        $this->assertSame(3, $attempts);
        $this->assertEqualsWithDelta(1.0, LazyStorage::RETRY, PHP_FLOAT_EPSILON);
    }

    public function testOwnExceptionAndBugsAreNotWrapped(): void
    {
        $own = new RateLimitStorageException('own');
        $storage = new LazyStorage(static fn () => throw $own);
        try {
            $storage->getStorage();
            $this->fail('a storage exception expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame($own, $e);
        }

        $bug = new \InvalidArgumentException('not a Redis client');
        $storage = new LazyStorage(static fn () => throw $bug);
        foreach ([1, 2] as $attempt) {
            try {
                $storage->getStorage();
                $this->fail('a bug is not hidden');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame($bug, $e, "attempt $attempt");
            }
        }

        $error = new \TypeError('wrong type');
        $storage = new LazyStorage(static fn () => throw $error);
        try {
            $storage->getStorage();
            $this->fail('an error is not hidden');
        } catch (\TypeError $e) {
            $this->assertSame($error, $e);
        }
    }
}
