<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use PHPUnit\Framework\Attributes\DataProvider;
use Nette\Caching\Storages\FileStorage;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;

/**
 * The shared expectations and invariants with the FileStorage of Nette Caching
 */
final class NetteCacheFileStorageTest extends StorageTestCase
{
    private string $directory;

    /** @var list<string> */
    private array $directories = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            exec('rm -rf ' . escapeshellarg($directory));
        }
    }

    #[\Override]
    protected function storage(): RateLimitStorageInterface
    {
        $this->directory = sys_get_temp_dir() . '/nette-api-rate-limit-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->directories[] = $this->directory;
        return new NetteCacheStorage(new FileStorage($this->directory));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function algorithmNames(): iterable
    {
        yield 'fixedWindow' => ['fixedWindow'];
        yield 'slidingWindow' => ['slidingWindow'];
        yield 'tokenBucket' => ['tokenBucket'];
    }

    /**
     * 8 processes send 25 requests each at the same moment, the file lock lets exactly 50 of the 200 through
     * (without it they read the same counts and 170-197 of the 200 passed)
     */
    #[DataProvider('algorithmNames')]
    public function testParallelClientsNeverExceedTheLimit(string $algorithm): void
    {
        $this->storage();
        $this->assertSame(50, ParallelClients::run('file', $this->directory, 'Parallel', $algorithm));
    }
}
