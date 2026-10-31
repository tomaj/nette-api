---
title: Rate limiting
description: 'Limit how often a Nette API endpoint may be called: fixed window, sliding window and token bucket, an app-wide default with per-endpoint overrides in neon or #[RateLimit], memory, Nette cache or atomic Redis storage, RateLimit headers and 429 with Retry-After.'
---

# Rate limiting

Since 4.0.0 the library has built-in rate limits. Set them in the `api:` section of the [DI extension](/guide/di-extension) or with [`#[RateLimit]`](/guide/attributes). Every part sits behind an interface, so you can plug in your own:

| Part | Built-in | Interface |
| --- | --- | --- |
| Algorithm | `fixedWindow`, `slidingWindow`, `tokenBucket` | `RateLimitAlgorithmInterface`, `RedisScriptAlgorithmInterface` |
| Storage | `cache` (Nette cache), `memory`, `redis` | `RateLimitStorageInterface`, `KeyValueStorage` |
| Key (who shares a quota) | `ip`, `identity`, `endpoint`, a list of them | `RateLimitKeyInterface` |
| Redis client | ext-redis (`\Redis`, `\RedisCluster`), Predis | `RedisClientInterface` |
| Limiter | `FixedWindowLimiter`, `SlidingWindowLimiter`, `TokenBucketLimiter`, `AlgorithmLimiter`, `RateLimiterChain` | `RateLimiterInterface` |

## Quick start

```yaml
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

api:
	rateLimits:
		perIp: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}
	defaults:
		rateLimit: perIp
```

Every endpoint now allows 60 requests per minute per IP. The counters are kept in the Nette cache, so nothing else is needed. Behind a reverse proxy add it to `http: proxy:`, otherwise all clients share the quota of the proxy, see [Which IP is counted?](#which-ip-is-counted).

## Global default and per-endpoint configuration

`api.rateLimits` defines named policies. `api.defaults.rateLimit` is the app-wide default for all endpoints. An endpoint overrides it, combines more policies, or turns it off:

```yaml
api:
	rateLimits:
		perIp: {algorithm: slidingWindow, limit: 100, interval: 1 minute}
		perUser: {algorithm: tokenBucket, limit: 1000, interval: 1 hour, key: identity}
		login: {algorithm: fixedWindow, limit: 5, interval: 15 minutes, key: [ip, endpoint]}

	defaults:
		rateLimit: perIp                     # all endpoints

	endpoints:
		# the default perIp
		- {method: GET, version: 1, package: users, handler: App\Api\UsersHandler}
		# override with another named policy
		- {method: POST, version: 1, package: login, handler: App\Api\LoginHandler, rateLimit: login}
		# more policies, checked in order, the request must pass each
		- {method: GET, version: 1, package: reports, handler: App\Api\ReportsHandler, rateLimit: [perIp, perUser]}
		# an inline limit only for this endpoint
		- {method: POST, version: 1, package: export, handler: App\Api\ExportHandler, rateLimit: {limit: 2, interval: 1 hour}}
		# no rate limit
		- {method: GET, version: 1, package: status, handler: App\Api\StatusHandler, rateLimit: false}
```

The same on a handler class with [attributes](/guide/attributes):

```php
#[RateLimit('login')]                                      // a named policy
#[RateLimit(['perIp', 'perUser'])]                         // more policies
#[RateLimit(limit: 2, interval: '1 hour')]                 // inline
#[RateLimit(limit: 1000, interval: '1 hour', algorithm: 'tokenBucket', key: 'identity', name: 'quota')]
#[RateLimit(false)]                                        // none
```

A named policy is one shared object: all endpoints with `rateLimit: perIp` share the counter of a client. An inline limit, in neon or in the attribute, has its own counters for each endpoint (the extension adds `endpoint` to its key); its policy name is the endpoint, for example `POST.v1.export`. `name` changes only the policy name in the headers, the counters stay per endpoint. Two named policies with the same `name` fail the compilation, they would share their counters; so does an inline `name` equal to the name of a named policy, use `rateLimit: perIp` to share the counters.

The policies of a list are checked in order. The first one that rejects the request answers 429 and the next ones are not counted, so rejected requests of one client do not use up a quota shared with others. Put the limits of one client before the shared ones, for example `[perIp, perEndpoint]`.

### The options of a policy

| Option | Default | Values |
| --- | --- | --- |
| `algorithm` | `slidingWindow` | `fixedWindow`, `slidingWindow`, `tokenBucket`, or a [custom algorithm](#a-custom-algorithm) |
| `limit` | required | requests per interval, from 1 to 1 000 000 000 |
| `interval` | required | seconds, or `30 seconds`, `1 minute`, `2 hours`, `1 day`; from 1 second to 366 days |
| `key` | `ip` | `ip`, `identity`, `endpoint`, a list like `[identity, endpoint]`, or a class implementing `RateLimitKeyInterface` |
| `name` | the neon name | the policy name in the headers and in the storage key: letters, digits, `_`, `-` and `.`; unique among the named policies |

A policy can also be a class implementing `RateLimiterInterface`, `@service` or `App\MyLimiter(arguments)`.

### Algorithms

| Algorithm | Behaviour | Good for |
| --- | --- | --- |
| `fixedWindow` | counts in windows that start at a multiple of the interval; cheap, but allows up to 2× the limit around the edge of two windows | simple quotas |
| `slidingWindow` | weights the previous window by how much of it still overlaps; smooth, no burst at the edge | the default for APIs |
| `tokenBucket` | `limit` tokens refill evenly over the interval; allows a burst of `limit`, then a steady rate | clients that send in batches |

## Before and after authorization

The presenter checks the rate limits twice: before the authorization and after a successful one. A policy keyed by `identity` runs after it, with the identity from `AuthorizationIdentityInterface` (`BearerTokenAuthorization`, `HeaderApiKeyAuthentication`, `BasicAuthentication`, ...). The other keys run before it, so a client guessing tokens is limited too. A request without an identity skips the `identity` policies. When the authorization implements the interface, but returns no identity after a successful `authorized()`, a warning goes to the log: a subclass that overrides `authorized()` without calling the parent must override `getIdentity()` too.

An endpoint's own `rateLimit` keyed by `identity` (also in a list or a named policy) fails the container compilation when its authorization is a known class without `AuthorizationIdentityInterface`, for example `authorization: false` or `NoAuthorization`, because the limit would never apply. `defaults.rateLimit` is not checked, as it is shared by endpoints with and without an authorization; neither is a service whose type is only an interface.

## Storages

```yaml
api:
	rateLimiting:
		storage: cache          # the default
		# storage: memory
		# storage: redis
		# redis: @redis         # \Redis, \RedisCluster, Predis\ClientInterface or a RedisClientInterface
		# prefix: 'myapp:rate-limit:'
		# clock: App\MyClock    # a Psr\Clock\ClockInterface
```

| Storage | Atomic | Use |
| --- | --- | --- |
| `cache` | yes on one server, a file lock; no between servers | one server; uses the Nette cache storage of the application |
| `memory` | yes, within one process | tests and long-running workers (expired counters are removed as it grows); with PHP-FPM each request starts empty |
| `redis` | yes, one Lua script per request | more servers, exact limits |

::: warning Nette cache is atomic only on one server
Nette cache has no increment, so `NetteCacheStorage` reads and writes the counter in two steps. On one server the steps run under a file lock: parallel requests wait for each other and the limit holds exactly. The lock files are in `%tempDir%/nette-api-rate-limit` (64 files shared by all keys), or without the DI extension in `nette-api-rate-limit-<uid>` of the system temp directory, one per user; pass the directory as the third argument of `NetteCacheStorage`, or `false` to turn the lock off. In the application directory the files get the permissions of the umask like the Nette cache; a web server and a cron of another user share them also with umask `022`, because a lock file that cannot be written is opened for reading, which is enough for the lock; the default directory is created with `0700` and its files with `0600`, and a file or directory there that belongs to another user is not used. When the directory cannot be written, or the file is not used, the counter is updated without the lock; a deleted lock file is created again. On a file system without locks (NFS without lockd) the counter is updated without the lock right away, the request does not wait. A request waits for the lock at most 3 seconds (the fourth argument `lockTimeout`), then the storage throws and the request passes with a warning in the log, as on any storage error. Servers sharing one cache (Memcached, Redis through nette/caching) do not share the lock, so their parallel requests can read the same value and all pass. Use `redis` when the limit must hold exactly across servers.

Every key is one cache item. `FileStorage` deletes an expired file when it is read again, and `SQLiteStorage` keeps expired rows, so the counters of clients that never come back stay until `$storage->clean([Cache::All => true])` or the cache is emptied. The `MemoryStorage` of nette/caching never removes expired items, so in a long-running worker it grows with every client; use `memory` there instead. `DevNullStorage` keeps nothing, so every request passes.
:::

The time comes from the clock of the application server, not from Redis. With more servers keep their clocks synchronized (NTP): a window starts at a different moment on a server whose clock is off. The token bucket never moves its time back, so a server behind the others does not refill a bucket twice. A server behind the others counts in the newer window and does not reset it, in every storage: in Redis the fixed and the sliding window keep the start of the newest window in the key `<key>:start`. `t`, `Retry-After` and the expiration of a counter are measured by the clock of the server that answers, so a client waiting `Retry-After` is not rejected again by the same server.

All built-in policies use the same storage. Redis keys are `prefix` + `{policy:key}`; the braces are a Redis Cluster hash tag, so every key of one counter is in one slot. Install `ext-redis` or `predis/predis` and register the client as a service:

```yaml
services:
	redis:
		factory: Redis
		setup:
			- connect('127.0.0.1', 6379, 1.0, null, 0, 1.0)   # 1 second to connect, 1 second to read
		autowired: false

api:
	rateLimiting:
		storage: redis
		redis: @redis
```

Set short timeouts. With PHP-FPM every request connects on its own, so a Redis that does not answer delays each rate limited request by the timeout. Without a timeout the requests wait for `default_socket_timeout` (60 seconds by default) and can use up all workers, even though they would pass in the end.

### When the storage fails

A rate limit must not take the API down. The storage is created on the first request with a rate limit, not with the API, so Redis that is down when the application starts does not fail the endpoints, and endpoints without a limit never connect to it; a long-running process tries an unsuccessful connection again after a second, with PHP-FPM every request tries once. When the storage throws (Redis is down, the cache directory is read only), the request passes (fail-open) and a warning with the cause goes to the Tracy log, or to the `Tracy\ILogger` you pass to the limiter. When the logger fails too (Tracy without a log directory), the warning goes to the PHP `error_log` and the request still passes. A `\LogicException` (a bug or a wrong configuration) is not hidden: like any exception of an own limiter or key, it goes to the `handle()` of the error handler, which answers `500`.

## Headers and 429

Every policy that applied adds the headers of [draft-ietf-httpapi-ratelimit-headers](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/):

```http
RateLimit-Policy: "perIp";q=100;w=60
RateLimit: "perIp";r=97;t=42
```

`q` is the limit, `w` the window in seconds, `r` the remaining requests and `t` the seconds until the quota is available again. When any policy rejects the request, the presenter answers `429 Too Many Requests` with `Retry-After` (the longest wait of the rejected policies) and the response of the error handler's `handleRateLimit()`, or your own response from `RateLimitResult::$errorResponse`. `ProblemDetailsErrorHandler` sends a [problem](/guide/problem-details) with `limit` and `retryAfter`.

## Own parts

### A custom key

```php
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;

final class TenantKey implements RateLimitKeyInterface
{
    public function key(RateLimitContext $context): ?string
    {
        return $context->request->getHeader('X-Tenant');   // null skips the limit
    }

    public function needsIdentity(): bool
    {
        return false;                                       // true runs it after the authorization
    }
}
```

```yaml
api:
	rateLimits:
		perTenant: {limit: 1000, interval: 1 hour, key: App\TenantKey}
		perTenantAndUser: {limit: 100, interval: 1 hour, key: [App\TenantKey, identity]}
```

A header comes from the client, which can send another value with every request; use it as a key only when your proxy or the authorization checks it. A key longer than 200 characters is hashed (SHA-256) before it goes to the storage. A list key joins its parts with `|` and escapes `|` and `\` in them, so `[App\TenantKey, identity]` with the tenant `a|identity:x` cannot share the counter of another tenant.

### A custom storage

Extend `KeyValueStorage` and implement `load()` and `save()`; every algorithm works with it. An exception from them (a `PDOException` during a database outage) becomes `RateLimitStorageException`, so the request passes as with the built-in storages. `load()` and `save()` run in two steps without a lock, so parallel requests can read the same value and all pass. Override `consume()` and lock around `parent::consume()`, like `NetteCacheStorage` does, or implement an atomic `RateLimitStorageInterface::consume()` yourself.

```php
use Tomaj\NetteApi\RateLimit\Storage\KeyValueStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;

final class MemcachedStorage extends KeyValueStorage
{
    public function __construct(private \Memcached $memcached)
    {
    }

    protected function load(string $key, float $now): mixed
    {
        $value = $this->memcached->get('rate:' . md5($key));
        return $value === false ? null : $value;
    }

    protected function save(string $key, mixed $value, int $ttl, float $now): void
    {
        // Memcached reads an expiration above 30 days as a timestamp, a relative one would expire at once
        $expiration = $ttl > 2_592_000 ? time() + $ttl : $ttl;
        if (!$this->memcached->set('rate:' . md5($key), $value, $expiration)) {
            throw new RateLimitStorageException('Memcached did not save the counter: ' . $this->memcached->getResultMessage());
        }
    }
}
```

```yaml
api:
	rateLimiting:
		storage: App\MemcachedStorage(@memcached)
```

Throw `RateLimitStorageException` when the storage is not available, so the request passes and the warning is logged.

### A custom Redis client

Implement `RedisClientInterface::evaluate(string $script, array $keys, array $arguments): mixed`, which runs `EVAL`, returns its reply unchanged and throws on an error reply, and pass it as `rateLimiting.redis`. The options of the built-in clients do not matter: the prefix, serializer and compression of ext-redis and the `prefix` and `exceptions: false` of Predis work.

### A custom limiter

Implement `RateLimiterInterface::consume(RateLimitContext $context, int $cost = 1): array` and return one `RateLimitResult` per policy, or `[]` when no limit applies in `$context->stage`. Use it as `rateLimits: {mine: App\MyLimiter}` or `#[RateLimit('App\MyLimiter')]`. The presenter removes characters other than printable ASCII from the policy name and escapes `"` and `\`, so the headers stay valid; negative numbers (`limit`, `remaining`, `resetAfter`, `retryAfter`) are sent as `0`. A `resetAfter` of `null` leaves `t` out of the `RateLimit` header.

## A custom algorithm

An algorithm decides; a storage only keeps its state. So your algorithm works with every storage, Redis included.

`RateLimitAlgorithmInterface::step()` gets the state it returned last time (`null` the first time or after it expired) and returns an `AlgorithmStep`: the result, the new state, and the seconds to keep it. A `null` state is not saved. `RedisStorage` cannot load and save in two steps atomically, so an algorithm for Redis also implements `RedisScriptAlgorithmInterface::redisScript()` with the same logic in Lua:

- `KEYS[1]` is the key of the counter. Write your own keys as `KEYS[1] .. ':name'`, so they stay in the same Redis Cluster slot and do not meet the keys of the built-in algorithms (the token bucket uses `KEYS[1]` itself, the windows `KEYS[1] .. ':start'` and `KEYS[1] .. ':<window start>'`) when you change the algorithm of a policy.
- `ARGV` are `limit`, `interval`, `cost` and `now` (a UNIX timestamp with microseconds).
- It returns `{accepted (1 or 0), remaining, resetAfter, retryAfter (-1 when accepted)}` as integers and sets an expiration on every key it writes.

The example allows one request every `interval / limit` seconds, so 60 a minute means one a second, without bursts:

```php
use Tomaj\NetteApi\RateLimit\Algorithm\RedisScriptAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Storage\AlgorithmStep;
use Tomaj\NetteApi\RateLimit\Storage\StorageResult;

final readonly class MinimumGap implements RedisScriptAlgorithmInterface
{
    public function step(mixed $state, int $limit, int $interval, int $cost, float $now): AlgorithmStep
    {
        $gap = $interval / $limit;
        // check the shape: the state may come from another algorithm after a config change
        if (is_float($state) && $now - $state < $gap) {
            $wait = (int) ceil($gap - ($now - $state));
            return new AlgorithmStep(new StorageResult(false, 0, $wait, $wait));   // rejected, nothing saved
        }

        // accepted: the state is the time of this request, kept for one gap plus a second
        return new AlgorithmStep(new StorageResult(true, 0, (int) ceil($gap), null), $now, (int) ceil($gap) + 1);
    }

    public function redisScript(): string
    {
        return <<<'LUA'
            local limit, interval, now = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[4])
            local gap = interval / limit
            local last = tonumber(redis.call('GET', KEYS[1] .. ':gap') or '')
            if last and now - last < gap then
                local wait = math.ceil(gap - (now - last))
                return {0, 0, wait, wait}
            end
            redis.call('SET', KEYS[1] .. ':gap', string.format('%.6f', now), 'EX', math.ceil(gap) + 1)
            return {1, 0, math.ceil(gap), -1}
            LUA;
    }
}
```

Use it by class name, `@service` or `App\MinimumGap(arguments)`, in neon or in the attribute:

```yaml
api:
	rateLimits:
		steady: {algorithm: App\MinimumGap, limit: 60, interval: 1 minute}
```

```php
#[RateLimit(limit: 60, interval: '1 minute', algorithm: MinimumGap::class)]
```

With `storage: redis`, or a `RedisStorage` of your own (`@service`, a class or a statement), the container fails to compile when the algorithm (a class, `@service` or a statement) has no Lua script, so you find out before deploying. In PHP use `new AlgorithmLimiter(new MinimumGap(), 'steady', 60, 60, new IpKey(), $storage)`.

### Testing the algorithm

Run the same scenario against every storage, so the PHP and the Lua versions cannot drift apart:

```php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Redis\PhpRedisClient;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;

final class MinimumGapTest extends TestCase
{
    public static function storages(): iterable
    {
        yield 'memory' => ['memory'];
        yield 'redis' => ['redis'];
    }

    #[DataProvider('storages')]
    public function testOneRequestPerGap(string $name): void
    {
        $storage = $name === 'memory' ? new InMemoryStorage() : $this->redisStorage();
        $consume = fn (float $now) => $storage->consume(new MinimumGap(), 'key', 2, 10, 1, $now);

        $this->assertTrue($consume(1000.0)->accepted);
        $rejected = $consume(1003.0);
        $this->assertFalse($rejected->accepted);
        $this->assertSame(2, $rejected->retryAfter);          // the gap is 10 / 2 = 5 seconds
        $this->assertTrue($consume(1005.0)->accepted);
    }

    private function redisStorage(): RateLimitStorageInterface
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1');
        return new RedisStorage(new PhpRedisClient($redis), 'test:' . uniqid() . ':');
    }
}
```

The time is a parameter, so the test needs no sleeping. In an application the limiter takes it from a `Psr\Clock\ClockInterface` (`rateLimiting.clock`).

## Without the DI extension

```php
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\SlidingWindowLimiter;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;

$limiter = new SlidingWindowLimiter('perIp', 60, 60, new IpKey(), new NetteCacheStorage($cacheStorage));
$apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users'), $handler, new NoAuthorization(), $limiter);
```

Register such a service with `autowired: false`, otherwise Nette DI passes it to every `addApi()` call.

The `ip` key needs an `IpDetectorInterface` service in the container (the DI extension always registers one). Without it, a request to an endpoint with an `ip` limit answers `500` through the error handler, with a `LogicException` that names the endpoint in the log, so the limit is not skipped silently; register `Tomaj\NetteApi\Misc\IpDetector` or your own detector. Limiters that don't use the IP keep working without it.

## Upgrading from RateLimitInterface

`RateLimitInterface::check()` from 3.x still works through `LegacyRateLimitAdapter`, also in a list like `rateLimit: [perIp, App\LegacyLimit]`, with an `E_USER_DEPRECATED` notice. Without a retry time it sends no `t`, the reset time is not known. It is removed in 5.0. The `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers are replaced by `RateLimit-Policy` and `RateLimit`. See the [upgrade guide](https://github.com/tomaj/nette-api/blob/master/UPGRADE-4.0.md).

## FAQ

### Which IP is counted?

With the default `IpDetector`, the address from `Nette\Http\IRequest::getRemoteAddress()`, not from the detector: it trusts the `Client-IP` and `X-Forwarded-For` headers, so any client would get a new quota with every value (see the warning in [Authorization](/guide/authorization#tokens-and-ip-restrictions)). Nette trusts the forwarded headers only from the proxies you list:

```yaml
http:
	proxy: 10.0.0.0/8      # your reverse proxy or load balancer
```

Without it, all clients behind a reverse proxy share one quota, the quota of the proxy. An own detector in `api.ipDetector` is used as it is, so it decides which headers to trust. A value longer than an IP (45 characters) is hashed, so forged headers cannot make long storage keys.

### Are 429 responses logged?

No, the presenter returns before the logger runs. See [Logging](/guide/logging).

### Can one request cost more than one?

Yes, `RateLimiterInterface::consume()` takes a `$cost`. The presenter uses 1.
