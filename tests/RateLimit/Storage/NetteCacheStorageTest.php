<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Storage;

use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\SlidingWindow;
use Tomaj\NetteApi\RateLimit\Interval;
use Nette\Caching\Cache;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;
use Nette\Caching\Storage;
use Nette\Caching\Storages\MemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;

final class NetteCacheStorageTest extends StorageTestCase
{
    #[\Override]
    protected function storage(): RateLimitStorageInterface
    {
        return new NetteCacheStorage(new MemoryStorage());
    }

    public function testUsesNamespaceAndExpiration(): void
    {
        $cache = new class extends MemoryStorage {
            /** @var array<string, array<mixed>> */
            public array $saved = [];

            public function write(string $key, $data, array $dependencies): void
            {
                $this->saved[$key] = $dependencies;
                parent::write($key, $data, $dependencies);
            }
        };
        new NetteCacheStorage($cache)->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1000.5);
        new NetteCacheStorage($cache, 'Own')->consume(new FixedWindow(), 'fixed', 3, 60, 1, 1000.5);

        $keys = array_keys($cache->saved);
        $this->assertCount(2, $keys);
        $this->assertStringStartsWith("Tomaj.NetteApi.RateLimit\x00", $keys[0]);
        $this->assertStringStartsWith("Own\x00", $keys[1]);
        $this->assertEqualsWithDelta(21, $cache->saved[$keys[1]][Cache::Expire], 1, 'the counter lives until the window ends + 1 second');
    }

    public function testCountersLongerThanAYearAreKept(): void
    {
        $storage = $this->storage();
        $now = (float) time();
        $this->assertTrue($storage->consume(new SlidingWindow(), 'year', 1, Interval::MAX, 1, $now)->accepted);
        $this->assertFalse($storage->consume(new SlidingWindow(), 'year', 1, Interval::MAX, 1, $now)->accepted, 'a TTL over one year is not read by Nette as a timestamp in the past');
    }

    public function testStorageErrorsBecomeRateLimitStorageException(): void
    {
        $broken = new class implements Storage {
            public function read(string $key): mixed
            {
                throw new \RuntimeException('disk full');
            }

            public function lock(string $key): void
            {
            }

            public function write(string $key, $data, array $dependencies): void
            {
                throw new \RuntimeException('read only');
            }

            public function remove(string $key): void
            {
            }

            public function clean(array $conditions): void
            {
            }
        };

        try {
            new NetteCacheStorage($broken)->consume(new FixedWindow(), 'fixed', 3, 60, 1, 0.0);
            $this->fail('read error expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame("The rate limit counter 'fixed' cannot be read from the storage Tomaj\\NetteApi\\RateLimit\\Storage\\NetteCacheStorage: disk full", $e->getMessage());
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame(0, $e->getCode());
        }

        $writeOnly = new class extends MemoryStorage {
            public function write(string $key, $data, array $dependencies): void
            {
                throw new \RuntimeException('read only');
            }
        };
        $this->expectException(RateLimitStorageException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage("The rate limit counter 'fixed' cannot be saved to the storage Tomaj\\NetteApi\\RateLimit\\Storage\\NetteCacheStorage: read only");
        new NetteCacheStorage($writeOnly)->consume(new FixedWindow(), 'fixed', 3, 60, 1, 0.0);
    }

    private static function lockIsFree(string $file): bool
    {
        $handle = fopen($file, 'c');
        self::assertIsResource($handle);
        $free = flock($handle, LOCK_EX | LOCK_NB);
        fclose($handle);
        return $free;
    }

    private static function lockDirectory(): string
    {
        return sys_get_temp_dir() . '/nette-api-lock-test-' . bin2hex(random_bytes(6)) . '/nested';
    }

    public function testCounterIsUpdatedUnderAFileLock(): void
    {
        $directory = self::lockDirectory();
        $file = $directory . '/' . (crc32('policy:key') % NetteCacheStorage::LOCKS) . '.lock';
        $this->assertSame(64, NetteCacheStorage::LOCKS);
        $states = [];
        $algorithm = new class ($file, $states) implements RateLimitAlgorithmInterface {
            /** @param list<bool> $states */
            public function __construct(private readonly string $file, public array &$states)
            {
            }

            public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
            {
                $this->states[] = NetteCacheStorageTest::isFree($this->file);
                return new AlgorithmStep(new StorageResult(true, 0, 0, null), 'x', 10);
            }
        };
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory);
        $storage->consume($algorithm, 'policy:key', 3, 60, 1, 0.0);
        $storage->consume($algorithm, 'policy:key', 3, 60, 1, 0.0);
        $this->assertSame([false, false], $states, 'the file is locked while the counter is read and saved');
        $this->assertTrue(self::lockIsFree($file), 'and unlocked after');
        $this->assertCount(1, new \ReflectionProperty(NetteCacheStorage::class, 'locks')->getValue($storage), 'the open lock file is reused');
        $this->assertSame(['.', '..', basename($file)], scandir($directory));

        $broken = new class extends MemoryStorage {
            public function write(string $key, $data, array $dependencies): void
            {
                throw new \RuntimeException('read only');
            }
        };
        try {
            new NetteCacheStorage($broken, lockDirectory: $directory)->consume(new FixedWindow(), 'policy:key', 3, 60, 1, 0.0);
            $this->fail('an error expected');
        } catch (RateLimitStorageException) {
            $this->assertTrue(self::lockIsFree($file), 'an error releases the lock');
        }
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public static function isFree(string $file): bool
    {
        return self::lockIsFree($file);
    }

    public function testLockCanBeTurnedOffOrMissing(): void
    {
        $directory = self::lockDirectory();
        $this->assertTrue(new NetteCacheStorage(new MemoryStorage(), lockDirectory: false)->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted);
        $this->assertTrue(new NetteCacheStorage(new MemoryStorage(), lockDirectory: '/proc/nette-api')->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted, 'without a lock file the counter is updated anyway');
        $this->assertDirectoryDoesNotExist($directory);

        $temp = dirname(self::lockDirectory());
        mkdir($temp);
        $this->assertSame('accepted 1', self::defaultLocks($temp));
        $default = $temp . '/nette-api-rate-limit-' . posix_geteuid();
        $file = $default . '/' . (crc32('k') % 64) . '.lock';
        $this->assertFileExists($file, 'a directory of the user in the system temp directory by default');
        $this->assertSame(0700, fileperms($default) & 0777, 'other users cannot open the lock files');
        $this->assertSame(0600, fileperms($file) & 0777);
        exec('rm -rf ' . escapeshellarg($temp));
    }

    private static function holder(string $file): mixed
    {
        $handle = fopen($file, 'c');
        self::assertIsResource($handle);
        self::assertTrue(flock($handle, LOCK_EX));
        return $handle;
    }

    public function testLockHeldTooLongLetsTheRequestFail(): void
    {
        $directory = self::lockDirectory();
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory, lockTimeout: 0.05);
        $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0);
        $file = $directory . '/' . (crc32('k') % 64) . '.lock';
        $holder = self::holder($file);
        $start = microtime(true);
        try {
            $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0);
            $this->fail('a timeout expected');
        } catch (RateLimitStorageException $e) {
            $elapsed = microtime(true) - $start;
            $this->assertSame("The rate limit lock '$file' is held by another request for more than 0.05 seconds. Check for hanging requests and the speed of the cache storage.", $e->getMessage());
            $this->assertGreaterThanOrEqual(0.05, $elapsed);
            $this->assertLessThan(1.0, $elapsed);
        }
        flock($holder, LOCK_UN);
        $this->assertTrue($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted, 'the lock works again after it is released');
        $this->assertSame(NetteCacheStorage::LOCK_TIMEOUT, new \ReflectionProperty(NetteCacheStorage::class, 'lockTimeout')->getValue(new NetteCacheStorage(new MemoryStorage())));
        $this->assertEqualsWithDelta(3.0, NetteCacheStorage::LOCK_TIMEOUT, PHP_FLOAT_EPSILON);
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public function testWaitsForALockOfAnotherProcess(): void
    {
        $directory = self::lockDirectory();
        mkdir($directory, 0700, true);
        $file = $directory . '/' . (crc32('k') % 64) . '.lock';
        $process = proc_open([PHP_BINARY, '-r', '$h = fopen($argv[1], "c"); flock($h, LOCK_EX); echo "locked\n"; usleep(300000);', $file], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $this->assertSame("locked\n", fgets($pipes[1]));
        $start = microtime(true);
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory);
        $this->assertTrue($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted);
        $this->assertGreaterThan(0.1, microtime(true) - $start, 'it waited for the other process');
        proc_close($process);
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public function testLockOfACrashedProcessIsReleased(): void
    {
        $directory = self::lockDirectory();
        mkdir($directory, 0700, true);
        $file = $directory . '/' . (crc32('k') % 64) . '.lock';
        $process = proc_open([PHP_BINARY, '-r', '$h = fopen($argv[1], "c"); flock($h, LOCK_EX); echo "locked\n"; posix_kill(getmypid(), SIGKILL);', $file], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $this->assertSame("locked\n", fgets($pipes[1]));
        proc_close($process);
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory, lockTimeout: 0.5);
        $this->assertTrue($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted, 'the system releases the lock of a killed process');
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public function testDeletedLockFileIsReplaced(): void
    {
        $directory = self::lockDirectory();
        $file = $directory . '/' . (crc32('policy:key') % 64) . '.lock';
        $states = [];
        $algorithm = new class ($file, $states) implements RateLimitAlgorithmInterface {
            /** @param list<bool> $states */
            public function __construct(private readonly string $file, public array &$states)
            {
            }

            public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
            {
                $this->states[] = NetteCacheStorageTest::isFree($this->file);
                return new AlgorithmStep(new StorageResult(true, 0, 0, null), 'x', 10);
            }
        };
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory);
        $storage->consume($algorithm, 'policy:key', 3, 60, 1, 0.0);
        unlink($file); // a temp cleaner or a deploy, other processes create a new file
        $storage->consume($algorithm, 'policy:key', 3, 60, 1, 0.0);
        exec('rm -rf ' . escapeshellarg($directory));
        $storage->consume($algorithm, 'policy:key', 3, 60, 1, 0.0);
        $this->assertSame([false, false, false], $states, 'the new file is locked');
        $this->assertTrue(self::lockIsFree($file));
        $this->assertCount(1, new \ReflectionProperty(NetteCacheStorage::class, 'locks')->getValue($storage));
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    /**
     * the locks used by consume('k') with the default lock directory in a process whose system temp directory is $temp
     */
    private static function defaultLocks(string $temp): string
    {
        $code = 'require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';'
            . '$s = new Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage(new Nette\Caching\Storages\MemoryStorage());'
            . 'echo $s->consume(new Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow(), "k", 3, 60, 1, 0.0)->accepted ? "accepted " : "rejected ";'
            . 'echo count((new ReflectionProperty($s, "locks"))->getValue($s));';
        return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d sys_temp_dir=' . escapeshellarg($temp) . ' -r ' . escapeshellarg($code));
    }

    public function testLockFilesOfAnotherUserAreNotUsed(): void
    {
        $temp = dirname(self::lockDirectory());
        $directory = $temp . '/nette-api-rate-limit-' . posix_geteuid();
        mkdir($directory, 0700, true);
        $this->assertSame('accepted 1', self::defaultLocks($temp), 'own files are used');

        unlink($directory . '/' . (crc32('k') % 64) . '.lock');
        symlink('/dev/null', $directory . '/' . (crc32('k') % 64) . '.lock'); // a file of root, everybody can open it
        $this->assertSame(0666, fileperms('/dev/null') & 0777);
        $this->assertSame('accepted 0', self::defaultLocks($temp), 'the counter is updated without the lock');

        exec('rm -rf ' . escapeshellarg($directory));
        symlink('/tmp', $directory); // a directory of root, other users could replace the files in it (sys_get_temp_dir() is ours on macOS)
        $this->assertNotSame(posix_geteuid(), fileowner('/tmp/.'));
        $this->assertSame('accepted 0', self::defaultLocks($temp));
        @unlink('/tmp/' . (crc32('k') % 64) . '.lock');
        exec('rm -rf ' . escapeshellarg($temp));
    }

    public function testLockDirectoryOfTheApplicationIsSharedByItsUsers(): void
    {
        $directory = self::lockDirectory();
        $umask = umask(0o022);
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory);
        $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0);
        umask($umask);
        $this->assertSame(0755, fileperms($directory) & 0777, 'a web server and a cron of other users can use it, like the Nette cache');
        $this->assertSame(0644, fileperms($directory . '/' . (crc32('k') % 64) . '.lock') & 0777);
        exec('rm -rf ' . escapeshellarg(dirname($directory)));

        mkdir($directory, 0777, true);
        symlink('/dev/null', $directory . '/' . (crc32('k') % 64) . '.lock'); // a file of another user
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory);
        $this->assertTrue($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted);
        $this->assertCount(1, new \ReflectionProperty(NetteCacheStorage::class, 'locks')->getValue($storage), 'it is locked');
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public function testReadOnlyLockFileOfAnotherUserStillLocks(): void
    {
        $directory = self::lockDirectory();
        mkdir($directory, 0777, true);
        $file = $directory . '/' . (crc32('k') % 64) . '.lock';
        touch($file);
        chmod($file, 0444); // created by another user with umask 022
        $this->assertFalse(@fopen($file, 'c'), 'it cannot be opened for writing');
        $holder = fopen($file, 'r');
        $this->assertIsResource($holder);
        $this->assertTrue(flock($holder, LOCK_EX));
        $storage = new NetteCacheStorage(new MemoryStorage(), lockDirectory: $directory, lockTimeout: 0.05);
        try {
            $storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0);
            $this->fail('the lock of the other request is respected');
        } catch (RateLimitStorageException $e) {
            $this->assertStringStartsWith("The rate limit lock '$file' is held by another request", $e->getMessage());
        }
        flock($holder, LOCK_UN);
        $this->assertTrue($storage->consume(new FixedWindow(), 'k', 3, 60, 1, 0.0)->accepted);
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public function testFileSystemWithoutLocksDoesNotWait(): void
    {
        $tryLock = \Closure::bind(static fn ($handle): ?bool => NetteCacheStorage::tryLock($handle), null, NetteCacheStorage::class);
        $wait = \Closure::bind(static fn ($handle, float $timeout): ?bool => NetteCacheStorage::wait($handle, $timeout), null, NetteCacheStorage::class);
        $memory = fopen('php://memory', 'r+'); // flock() is not supported, like on NFS without lockd
        $this->assertNull($tryLock($memory));
        $start = microtime(true);
        $this->assertNull($wait($memory, 1.0));
        $this->assertLessThan(0.5, microtime(true) - $start, 'it does not wait for the timeout');

        $directory = self::lockDirectory();
        mkdir($directory, 0700, true);
        $file = $directory . '/x.lock';
        $first = fopen($file, 'c');
        $second = fopen($file, 'c');
        $this->assertTrue($tryLock($first));
        $this->assertFalse($tryLock($second), 'held by another request');
        $this->assertFalse($wait($second, 0.01));
        $this->assertTrue($wait($first, 0.01));
        fclose($first);
        $this->assertTrue($wait($second, 0.01));
        exec('rm -rf ' . escapeshellarg(dirname($directory)));
    }

    public function testLockFilesSpreadOverAllLocks(): void
    {
        $used = [];
        for ($i = 0; $i < 10_000; $i++) {
            $index = crc32('ip:192.168.' . intdiv($i, 256) . '.' . ($i % 256)) % NetteCacheStorage::LOCKS;
            $used[$index] = ($used[$index] ?? 0) + 1;
        }
        $this->assertCount(64, $used);
        $this->assertLessThan(2 * 10_000 / 64, max($used), 'no lock is shared by much more keys than the others');
    }
}
