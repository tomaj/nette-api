<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;

final class InMemoryStorageTest extends StorageTestCase
{
    #[\Override]
    protected function storage(): RateLimitStorageInterface
    {
        return new InMemoryStorage();
    }

    public function testValuesExpireAndClear(): void
    {
        $storage = new InMemoryStorage();
        $storage->consume(new TokenBucket(), 'bucket', 2, 10, 2, 100.0);
        $this->assertFalse($storage->consume(new TokenBucket(), 'bucket', 2, 10, 1, 100.0)->accepted);
        $this->assertTrue($storage->consume(new TokenBucket(), 'bucket', 2, 10, 2, 111.0)->accepted, 'the state expires after interval + 1 second');

        $storage->consume(new FixedWindow(), 'fixed', 1, 60, 1, 0.0);
        $this->assertFalse($storage->consume(new FixedWindow(), 'fixed', 1, 60, 1, 0.0)->accepted);
        $storage->clear();
        $this->assertTrue($storage->consume(new FixedWindow(), 'fixed', 1, 60, 1, 0.0)->accepted);
    }

    public function testValueExpiresExactlyAtItsTtl(): void
    {
        $storage = new InMemoryStorage();
        $storage->consume(new FixedWindow(), 'fixed', 1, 60, 1, 30.0);
        $this->assertFalse($storage->consume(new FixedWindow(), 'fixed', 1, 60, 1, 59.9)->accepted);
        $load = new \ReflectionMethod(InMemoryStorage::class, 'load');
        $this->assertSame([0, 1], $load->invoke($storage, 'fixed', 60.9), 'kept for resetAfter 30 + 1 second');
        $this->assertNull($load->invoke($storage, 'fixed', 61.0), 'gone at the expiry');
    }

    public function testExpiredValuesArePrunedWhenTheStorageGrows(): void
    {
        $storage = new InMemoryStorage();
        $values = new \ReflectionProperty(InMemoryStorage::class, 'values');
        for ($i = 0; $i < 999; $i++) {
            $storage->consume(new FixedWindow(), 'old' . $i, 1, 60, 1, 0.0);
        }
        $this->assertCount(999, $values->getValue($storage), 'nothing is pruned below 1000 values');
        $storage->consume(new FixedWindow(), 'new', 1, 60, 1, 61.0);
        $this->assertSame(['new'], array_keys($values->getValue($storage)), 'the values expiring now are removed at 1000 values');
        $this->assertSame(1000, new \ReflectionProperty(InMemoryStorage::class, 'pruneAt')->getValue($storage), 'at least 1000');
        for ($i = 0; $i < 999; $i++) {
            $storage->consume(new FixedWindow(), 'next' . $i, 1, 60, 1, 61.0);
        }
        $this->assertCount(1000, $values->getValue($storage), 'values that did not expire are kept');
    }

    public function testLiveValuesRaiseTheNextPrune(): void
    {
        $storage = new InMemoryStorage();
        $values = new \ReflectionProperty(InMemoryStorage::class, 'values');
        for ($i = 0; $i < 1500; $i++) {
            $storage->consume(new FixedWindow(), 'live' . $i, 1, 60, 1, 0.0);
        }
        $this->assertSame(2000, new \ReflectionProperty(InMemoryStorage::class, 'pruneAt')->getValue($storage), 'twice the live values');
        $storage->consume(new FixedWindow(), 'expired', 1, 60, 1, 0.0);
        $this->assertCount(1501, $values->getValue($storage));
    }
}
