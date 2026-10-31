<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Nette\Caching\Storages\SQLiteStorage;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;

/**
 * The shared expectations and invariants with the SQLiteStorage of Nette Caching
 */
final class NetteCacheSQLiteStorageTest extends StorageTestCase
{
    #[\Override]
    protected function storage(): RateLimitStorageInterface
    {
        return new NetteCacheStorage(new SQLiteStorage(':memory:'));
    }
}
