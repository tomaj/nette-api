<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;

/**
 * Counters in any Nette\Caching\Storage (FileStorage, the Redis or Memcached storage of nette/caching, ...).
 *
 * Nette cache has no increment, so the counter is read and saved in two steps. The steps run under a file lock,
 * so parallel requests on one server wait for each other (at most LOCK_TIMEOUT seconds); servers sharing one cache (Memcached, ...) still race.
 * Use RedisStorage when the limit must hold exactly across servers.
 */
final class NetteCacheStorage extends KeyValueStorage
{
    public const string NAMESPACE = 'Tomaj.NetteApi.RateLimit';

    /** the number of lock files, keys share them, so the files do not grow with the keys */
    public const int LOCKS = 64;

    /** seconds to wait for the lock of a counter, then the request passes and the presenter logs a warning */
    public const float LOCK_TIMEOUT = 3.0;

    private readonly Cache $cache;

    private readonly string|false $lockDirectory;

    /** @var array<int, resource> open lock files */
    private array $locks = [];

    /** the lock files are in the shared system temp directory, so only files of the current user are used */
    private readonly bool $private;

    /**
     * @param string|false|null $lockDirectory the directory of the lock files, null for a directory of the current user in the system temp directory, false for no lock
     * @param float $lockTimeout seconds to wait for a lock held by another request
     */
    public function __construct(
        Storage $storage,
        string $namespace = self::NAMESPACE,
        string|false|null $lockDirectory = null,
        private readonly float $lockTimeout = self::LOCK_TIMEOUT,
    ) {
        $this->cache = new Cache($storage, $namespace);
        $this->private = $lockDirectory === null;
        $this->lockDirectory = $lockDirectory ?? sys_get_temp_dir() . '/nette-api-rate-limit-' . self::user();
    }

    #[\Override]
    public function consume(RateLimitAlgorithmInterface $algorithm, string $key, int $limit, int $interval, int $cost, float $now): StorageResult
    {
        $lock = $this->lock($key);
        try {
            return parent::consume($algorithm, $key, $limit, $interval, $cost, $now);
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
            }
        }
    }

    /**
     * @return resource|null the locked file, null when the file cannot be used or locked (the counter is then updated without the lock)
     */
    private function lock(string $key)
    {
        if ($this->lockDirectory === false) {
            return null;
        }
        $index = crc32($key) % self::LOCKS;
        $file = $this->lockDirectory . '/' . $index . '.lock';
        $handle = $this->locks[$index] ?? $this->open($file);
        if ($handle === null) {
            return null;
        }
        $this->locks[$index] = $handle;
        $locked = self::wait($handle, $this->lockTimeout);
        if ($locked === null) {
            return null; // the file system cannot lock (NFS without lockd, ...), waiting would only slow every request down
        }
        if (!$locked) {
            throw new RateLimitStorageException(sprintf("The rate limit lock '%s' is held by another request for more than %s seconds. Check for hanging requests and the speed of the cache storage.", $file, $this->lockTimeout));
        }
        clearstatcache(true, $file);
        if (@fileinode($file) !== ((fstat($handle) ?: [])['ino'] ?? null)) {
            // the file was deleted (a temp cleaner, a deploy) and other requests lock a new one
            flock($handle, LOCK_UN);
            fclose($handle);
            unset($this->locks[$index]);
            return $this->lock($key);
        }

        return $handle;
    }

    /**
     * Polls the lock every millisecond, flock() itself cannot wait for a limited time.
     *
     * @infection-ignore-all the poll interval and the comparison with the deadline only change the timing by a millisecond
     * @param resource $handle
     * @return bool|null true when locked, false when another request held the lock for the whole timeout, null when the file cannot be locked
     */
    private static function wait($handle, float $timeout): ?bool
    {
        $deadline = microtime(true) + $timeout;
        while (($locked = self::tryLock($handle)) === false) {
            if (microtime(true) >= $deadline) {
                return false;
            }
            usleep(1000);
        }

        return $locked;
    }

    /**
     * @param resource $handle
     * @return bool|null true when locked, false when another request holds the lock, null when the file cannot be locked at all
     */
    private static function tryLock($handle): ?bool
    {
        if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            return true;
        }
        // flock() fails without "would block" on a file system without locks, the lock would never be acquired
        return $wouldBlock === 1 ? false : null;
    }

    /**
     * @return resource|null
     */
    private function open(string $file)
    {
        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, $this->private ? 0700 : 0777, true); // @ - another process may create it at the same time
        }
        // @ - a missing lock only means the old race; a file of another user of the application (a web server
        // and a cron with umask 022) cannot be opened for writing, but flock() works on a file opened for reading
        $handle = @fopen($file, 'c') ?: @fopen($file, 'r');
        if ($handle === false) {
            return null;
        }
        if (!$this->private) {
            return $handle; // the directory of the application, shared by its users (a web server and a cron) like the Nette cache
        }
        $user = self::user();
        if ($user !== '' && (((fstat($handle) ?: [])['uid'] ?? null) !== (int) $user || @fileowner($directory) !== (int) $user)) {
            fclose($handle); // another user could hold the lock forever
            return null;
        }
        @chmod($file, 0600); // @ - only another user can lock it, and then the file is not ours anyway

        return $handle;
    }

    /**
     * the effective user id, '' when the posix extension is missing
     */
    private static function user(): string
    {
        return function_exists('posix_geteuid') ? (string) posix_geteuid() : '';
    }

    #[\Override]
    protected function load(string $key, float $now): mixed
    {
        return $this->cache->load($key);
    }

    #[\Override]
    protected function save(string $key, mixed $value, int $ttl, float $now): void
    {
        // an absolute time: Nette reads a number above one year as a timestamp, not as seconds
        $this->cache->save($key, $value, [Cache::Expire => time() + $ttl]);
    }
}
