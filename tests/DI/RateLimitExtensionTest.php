<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Attribute\RateLimit;
use Nette\DI\Container;
use Nette\DI\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\RateLimit\AlgorithmLimiter;
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\Test\RateLimit\Algorithm\MinimumGap;
use Tomaj\NetteApi\Test\RateLimit\ContextFactory;
use Tomaj\NetteApi\RateLimit\Key\CompositeKey;
use Tomaj\NetteApi\RateLimit\Key\EndpointKey;
use Tomaj\NetteApi\RateLimit\Key\IdentityKey;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\LegacyRateLimitAdapter;
use Tomaj\NetteApi\RateLimit\Storage\LazyStorage;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\RateLimit\RateLimiterChain;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\SlidingWindowLimiter;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;
use Tomaj\NetteApi\RateLimit\StorageRateLimiter;
use Tomaj\NetteApi\RateLimit\TokenBucketLimiter;
use Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit;
use Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit;
use Tomaj\NetteApi\Test\DI\Fixtures\DownRedisClient;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageException;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;

final class RateLimitExtensionTest extends TestCase
{
    private const string ECHO = EchoHandler::class;

    /** {handler} in the neon is replaced with EchoHandler */
    private static function neon(string $neon): string
    {
        return str_replace('{handler}', self::ECHO, $neon);
    }

    /**
     * @return list<RateLimiterInterface>
     */
    private function rateLimits(Container $container): array
    {
        return array_map(fn(Api $api): RateLimiterInterface => $api->getRateLimit(), $container->getByType(ApiDecider::class)->getApis());
    }

    private function storage(StorageRateLimiter $limiter): RateLimitStorageInterface
    {
        $value = new ReflectionProperty(StorageRateLimiter::class, 'storage')->getValue($limiter);
        $this->assertInstanceOf(LazyStorage::class, $value, 'the storage is created on the first use');
        return $value->getStorage();
    }

    public function testNamedInlineAndListLimits(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            api:
                rateLimiting: {storage: memory}
                rateLimits:
                    perIp: {algorithm: fixedWindow, limit: 100, interval: 1 minute}
                    perUser: {algorithm: tokenBucket, limit: 1000, interval: 3600, key: identity, name: user-quota}
                    both: {limit: 5, interval: 2 hours, key: [ip, identity]}
                    legacy: Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit(3)
                    own: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit(7)
                defaults:
                    authorization: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization
                    rateLimit: perIp
                endpoints:
                    - {method: GET, version: 1, package: defaults, handler: {handler}}
                    - {method: GET, version: 1, package: list, action: all, handler: {handler}, rateLimit: [perIp, perUser, own]}
                    - {method: POST, version: 2, package: inline, action: create, handler: {handler}, rateLimit: {limit: 3, interval: 10 seconds}}
                    - {method: GET, version: 1, package: both, handler: {handler}, rateLimit: both}
                    - {method: GET, version: 1, package: legacy, handler: {handler}, rateLimit: legacy}
                    - {method: GET, version: 1, package: 'off', handler: {handler}, rateLimit: false}
            NEON));
        [$default, $list, $inline, $both, $legacy, $off] = $this->rateLimits($container);

        $this->assertInstanceOf(FixedWindowLimiter::class, $default);
        $this->assertSame($container->getService('api.rateLimit.perIp'), $default, 'a name is one shared limiter');
        $this->assertSame(['perIp', 100, 60], [$default->getPolicy(), $default->getLimit(), $default->getInterval()]);
        $this->assertInstanceOf(IpKey::class, $default->getKey());
        $this->assertInstanceOf(InMemoryStorage::class, $this->storage($default));

        $this->assertInstanceOf(RateLimiterChain::class, $list);
        [$perIp, $perUser, $own] = $list->getLimiters();
        $this->assertSame($default, $perIp);
        $this->assertInstanceOf(TokenBucketLimiter::class, $perUser);
        $this->assertSame(['user-quota', 1000, 3600], [$perUser->getPolicy(), $perUser->getLimit(), $perUser->getInterval()]);
        $this->assertInstanceOf(IdentityKey::class, $perUser->getKey());
        $this->assertSame($this->storage($default), $this->storage($perUser), 'one storage for all limiters');
        $this->assertInstanceOf(FixedRateLimit::class, $own);

        $this->assertInstanceOf(SlidingWindowLimiter::class, $inline, 'slidingWindow is the default algorithm');
        $this->assertSame(['POST.v2.inline.create', 3, 10], [$inline->getPolicy(), $inline->getLimit(), $inline->getInterval()]);
        $inlineKey = $inline->getKey();
        $this->assertInstanceOf(CompositeKey::class, $inlineKey, 'ip is the default key, an inline limit counts per endpoint');
        $this->assertSame('ip:1.2.3.4|' . new EndpointKey()->key(ContextFactory::create()), $inlineKey->key(ContextFactory::create()));

        $this->assertInstanceOf(SlidingWindowLimiter::class, $both);
        $this->assertInstanceOf(CompositeKey::class, $both->getKey());
        $this->assertSame(7200, $both->getInterval());

        $this->assertInstanceOf(LegacyRateLimitAdapter::class, $legacy);
        $this->assertInstanceOf(LegacyRateLimit::class, $legacy->getRateLimit());
        $this->assertInstanceOf(NoRateLimit::class, $off);
    }

    public function testLegacyNamedServiceIsDeprecated(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            api:
                rateLimits:
                    legacy: Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit(3)
                endpoints:
                    - {method: GET, version: 1, package: legacy, handler: {handler}, rateLimit: legacy}
            NEON));
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = [$level, $message];
            return true;
        }, E_USER_DEPRECATED);
        try {
            $container->getService('api.rateLimit.legacy');
        } finally {
            restore_error_handler();
        }
        $this->assertSame([[E_USER_DEPRECATED, LegacyRateLimitAdapter::deprecation(LegacyRateLimit::class)]], $deprecations);
    }

    public function testLegacyRateLimitInAListIsAdapted(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                oldLimit: Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit(5)
            api:
                rateLimiting: {storage: memory}
                rateLimits:
                    perIp: {limit: 100, interval: 1 minute}
                    legacy: Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit(3)
                endpoints:
                    - {method: GET, version: 1, package: list, handler: {handler}, rateLimit: [perIp, legacy, Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit, @oldLimit, Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit]}
            NEON));
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_USER_DEPRECATED);
        try {
            [$list] = $this->rateLimits($container);
        } finally {
            restore_error_handler();
        }
        $this->assertInstanceOf(RateLimiterChain::class, $list);
        [$perIp, $named, $class, $service, $own] = $list->getLimiters();
        $this->assertSame($container->getService('api.rateLimit.perIp'), $perIp);
        $this->assertSame($container->getService('api.rateLimit.legacy'), $named, 'a named one is not adapted twice');
        $this->assertInstanceOf(LegacyRateLimitAdapter::class, $named);
        $this->assertInstanceOf(LegacyRateLimitAdapter::class, $class, 'a class of 3.x in the list works through the adapter');
        $this->assertInstanceOf(LegacyRateLimit::class, $class->getRateLimit());
        $this->assertInstanceOf(LegacyRateLimitAdapter::class, $service);
        $this->assertSame($container->getService('oldLimit'), $service->getRateLimit());
        $this->assertInstanceOf(FixedRateLimit::class, $own, 'a new limiter stays as it is');
        $this->assertSame(array_fill(0, 3, LegacyRateLimitAdapter::deprecation(LegacyRateLimit::class)), $deprecations);
    }

    public function testDefaultInlineLimitIsPerEndpoint(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                - Nette\Caching\Storages\MemoryStorage
            api:
                defaults:
                    rateLimit: {limit: 10, interval: 60}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}}
                    - {method: GET, version: 1, package: orders, action: list, handler: {handler}}
            NEON));
        [$users, $orders] = $this->rateLimits($container);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $users);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $orders);
        $this->assertSame('GET.v1.users', $users->getPolicy());
        $this->assertSame('GET.v1.orders.list', $orders->getPolicy());
        $this->assertInstanceOf(NetteCacheStorage::class, $this->storage($users), 'the Nette cache is the default storage');
    }

    public function testCacheLocksAreInTheTempDir(): void
    {
        $neon = <<<NEON
            services:
                - Nette\Caching\Storages\MemoryStorage
            api:
                defaults:
                    rateLimit: {limit: 10, interval: 60}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}}
            NEON;
        $lockDirectory = new ReflectionProperty(NetteCacheStorage::class, 'lockDirectory');
        $withTempDir = $this->rateLimits(ContainerFactory::create(self::neon("parameters:\n    tempDir: /app/temp\n" . $neon)))[0];
        $this->assertInstanceOf(StorageRateLimiter::class, $withTempDir);
        $this->assertSame('/app/temp/nette-api-rate-limit', $lockDirectory->getValue($this->storage($withTempDir)));
        $without = $this->rateLimits(ContainerFactory::create(self::neon($neon)))[0];
        $this->assertInstanceOf(StorageRateLimiter::class, $without);
        $this->assertSame(sys_get_temp_dir() . '/nette-api-rate-limit-' . posix_geteuid(), $lockDirectory->getValue($this->storage($without)));
    }

    public function testInlineLimitWithANameKeepsCountersPerEndpoint(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            api:
                rateLimiting: {storage: memory}
                defaults:
                    authorization: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization
                    rateLimit: {limit: 1, interval: 60, name: shared}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}}
                    - {method: GET, version: 1, package: orders, handler: {handler}}
                    - {method: GET, version: 1, package: both, handler: {handler}, rateLimit: {limit: 1, interval: 60, key: [identity, endpoint], name: shared}}
                    - {method: GET, version: 1, package: pair, handler: {handler}, rateLimit: {limit: 1, interval: 60, key: [ip, identity], name: pair}}
            NEON));
        [$users, $orders, $both, $pair] = $this->rateLimits($container);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $users);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $orders);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $both);
        $this->assertSame(['shared', 'shared'], [$users->getPolicy(), $orders->getPolicy()], 'the name is the policy in the headers');
        $key = $users->getKey();
        $this->assertInstanceOf(CompositeKey::class, $key);
        $endpoint = new EndpointKey()->key(ContextFactory::create());
        $this->assertSame('ip:1.2.3.4|' . $endpoint, $key->key(ContextFactory::create()), 'the endpoint is added to the key');
        $this->assertTrue($users->consume(ContextFactory::create())[0]->accepted);
        $this->assertTrue($orders->consume(ContextFactory::create(action: 'orders'))[0]->accepted, 'another endpoint has its own counter');
        $this->assertFalse($users->consume(ContextFactory::create())[0]->accepted);
        $bothKey = $both->getKey();
        $this->assertInstanceOf(CompositeKey::class, $bothKey);
        $this->assertTrue($bothKey->needsIdentity());
        $this->assertSame('identity:' . hash('sha256', 'me') . '|' . $endpoint, $bothKey->key(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'me')), 'an endpoint already in the key is not added twice');
        $this->assertInstanceOf(SlidingWindowLimiter::class, $pair);
        $pairKey = $pair->getKey();
        $this->assertInstanceOf(CompositeKey::class, $pairKey);
        $this->assertSame('ip:1.2.3.4|identity:' . hash('sha256', 'me') . '|' . $endpoint, $pairKey->key(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'me')), 'every key of the list is kept');
    }

    public function testRedisStorage(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                redis: Tomaj\NetteApi\Test\DI\Fixtures\FakeRedisClient
            api:
                rateLimiting: {storage: redis, redis: @redis, prefix: 'app:'}
                rateLimits:
                    perIp: {limit: 10, interval: 60}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: perIp}
            NEON));
        [$limiter] = $this->rateLimits($container);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $limiter);
        $storage = $this->storage($limiter);
        $this->assertInstanceOf(RedisStorage::class, $storage);
        $this->assertSame('app:', new ReflectionProperty(RedisStorage::class, 'prefix')->getValue($storage));
        $this->assertSame($container->getService('redis'), new ReflectionProperty(RedisStorage::class, 'client')->getValue($storage));

        $container = ContainerFactory::create(self::neon(<<<NEON
            api:
                rateLimiting: {storage: redis, redis: Tomaj\NetteApi\Test\DI\Fixtures\FakeRedisClient}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: {limit: 1, interval: 1}}
            NEON));
        [$limiter] = $this->rateLimits($container);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $limiter);
        $this->assertSame(RedisStorage::PREFIX, new ReflectionProperty(RedisStorage::class, 'prefix')->getValue($this->storage($limiter)));
    }

    public function testRedisIsConnectedOnTheFirstRateLimitedRequest(): void
    {
        DownRedisClient::$connections = 0;
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                redis:
                    factory: Tomaj\NetteApi\Test\DI\Fixtures\DownRedisClient
                    setup:
                        - connect()
            api:
                rateLimiting: {storage: redis, redis: @redis}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: {limit: 1, interval: 1}}
                    - {method: GET, version: 1, package: free, handler: {handler}}
            NEON));
        [$limiter] = $this->rateLimits($container);
        $this->assertSame(0, DownRedisClient::$connections, 'the API and its limiters are created without Redis');
        $this->assertInstanceOf(StorageRateLimiter::class, $limiter);
        $storage = new ReflectionProperty(StorageRateLimiter::class, 'storage')->getValue($limiter);
        $this->assertInstanceOf(LazyStorage::class, $storage);
        try {
            $storage->consume(new FixedWindow(), 'k', 1, 1, 1, 0.0);
            $this->fail('a storage outage expected');
        } catch (RateLimitStorageException $e) {
            $this->assertSame('The rate limit storage cannot be created: Connection refused', $e->getMessage());
        }
        $this->assertSame(1, DownRedisClient::$connections);
        $this->assertFalse($container->isCreated('api.rateLimiting.storageFactory'), 'the next request tries again');
    }

    public function testOwnStorageAndClock(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                clock: Tomaj\NetteApi\RateLimit\SystemClock
            api:
                rateLimiting: {storage: Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage, clock: @clock}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: {limit: 1, interval: 1}}
            NEON));
        [$limiter] = $this->rateLimits($container);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $limiter);
        $this->assertInstanceOf(InMemoryStorage::class, $this->storage($limiter));
        $this->assertSame($container->getService('clock'), new ReflectionProperty(StorageRateLimiter::class, 'clock')->getValue($limiter));
    }

    public function testStorageAsStatementIsNotAutowired(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            api:
                rateLimiting: {storage: Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage()}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: {limit: 1, interval: 1}}
            NEON));
        [$limiter] = $this->rateLimits($container);
        $this->assertInstanceOf(SlidingWindowLimiter::class, $limiter);
        $this->assertInstanceOf(InMemoryStorage::class, $this->storage($limiter));
        $this->assertNull($container->getByType(RateLimitStorageInterface::class, false), 'the storage does not take over autowiring in the application');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function attributeErrors(): iterable
    {
        yield 'name and limit' => ['RateLimitBoth', "Invalid endpoint attribute on Tomaj\\NetteApi\\Test\\DI\\Discovery\\RateLimitBoth\\Handler (#[RateLimit] has both a rate limit name and options of an inline limit (limit, interval, algorithm, key, name), which would be ignored."];
        yield 'not a name in the list' => ['RateLimitBadList', "Item 0 of the rate limit list in the rateLimit of endpoint GET /v1/users (#[Get] on Tomaj\\NetteApi\\Test\\DI\\Discovery\\RateLimitBadList\\Handler) is int, but it must be a name from api.rateLimits, @service or a class, for example rateLimit: [perIp, perUser] or #[RateLimit(['perIp', 'perUser'])]."];
    }

    #[DataProvider('attributeErrors')]
    public function testAttributeErrors(string $dir, string $message): void
    {
        $path = __DIR__ . '/Discovery/' . $dir;
        try {
            ContainerFactory::compile("api:\n    rateLimiting: {storage: memory}\n    discovery: {paths: [$path]}");
            $this->fail('the compilation should fail');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function testIdentityLimitsThatCanApplyCompile(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                identity: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization
                unknown:
                    type: Tomaj\NetteApi\Authorization\ApiAuthorizationInterface
                    factory: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization
            api:
                rateLimiting: {storage: memory}
                rateLimits:
                    perIp: {limit: 1, interval: 1}
                    perUser: {limit: 1, interval: 1, key: identity}
                    own: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit(7)
                authorizations:
                    user: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization
                defaults:
                    authorization: Tomaj\NetteApi\Authorization\NoAuthorization
                    rateLimit: perUser
                endpoints:
                    - {method: GET, version: 1, package: default, handler: {handler}}
                    - {method: GET, version: 1, package: named, handler: {handler}, authorization: user, rateLimit: [perIp, perUser]}
                    - {method: GET, version: 1, package: service, handler: {handler}, authorization: @identity, rateLimit: perUser}
                    - {method: GET, version: 1, package: interface, handler: {handler}, authorization: @unknown, rateLimit: perUser}
                    - {method: GET, version: 1, package: ip, handler: {handler}, rateLimit: [perIp, own]}
                    - {method: GET, version: 1, package: inline, handler: {handler}, rateLimit: {limit: 1, interval: 1}}
            NEON));
        $this->assertCount(6, $this->rateLimits($container), 'a default identity limit and an identity authorization compile');
    }

    public function testNoStorageWithoutBuiltInLimiters(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            api:
                rateLimits:
                    own: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit(7)
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: own}
            NEON));
        $this->assertFalse($container->hasService('api.rateLimiting.storage'), 'no cache needed for own limiters');
    }

    public function testCustomAlgorithm(): void
    {
        $container = ContainerFactory::create(self::neon(<<<NEON
            services:
                gapAlgorithm: Tomaj\NetteApi\Test\RateLimit\Algorithm\MinimumGap
            api:
                rateLimiting: {storage: redis, redis: Tomaj\NetteApi\Test\DI\Fixtures\FakeRedisClient}
                rateLimits:
                    byClass: {algorithm: Tomaj\NetteApi\Test\RateLimit\Algorithm\MinimumGap, limit: 2, interval: 10 seconds, key: identity}
                    byService: {algorithm: @gapAlgorithm, limit: 3, interval: 1 minute}
                    byStatement: {algorithm: Tomaj\NetteApi\Test\RateLimit\Algorithm\MinimumGap(), limit: 4, interval: 60}
                defaults: {authorization: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization}
                endpoints:
                    - {method: GET, version: 1, package: a, handler: {handler}, rateLimit: byClass}
                    - {method: GET, version: 1, package: b, handler: {handler}, rateLimit: byService}
                    - {method: GET, version: 1, package: c, handler: {handler}, rateLimit: byStatement}
                    - {method: GET, version: 1, package: d, handler: {handler}, rateLimit: {algorithm: Tomaj\NetteApi\Test\RateLimit\Algorithm\MinimumGap, limit: 1, interval: 1}}
            NEON));
        $algorithm = new ReflectionProperty(AlgorithmLimiter::class, 'algorithm');
        $limiters = $this->rateLimits($container);
        foreach ($limiters as $limiter) {
            $this->assertInstanceOf(AlgorithmLimiter::class, $limiter);
            $this->assertInstanceOf(MinimumGap::class, $algorithm->getValue($limiter));
            $this->assertInstanceOf(RedisStorage::class, $this->storage($limiter));
        }
        [$byClass, $byService, , $inline] = $limiters;
        $this->assertSame(['byClass', 2, 10], [$byClass->getPolicy(), $byClass->getLimit(), $byClass->getInterval()]);
        $this->assertInstanceOf(IdentityKey::class, $byClass->getKey());
        $this->assertSame($container->getService('gapAlgorithm'), $algorithm->getValue($byService));
        $this->assertSame('GET.v1.d', $inline->getPolicy());
        $this->assertTrue($byClass->consume(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'u1'))[0]->accepted, 'the fake Redis accepts');
    }

    public function testAttribute(): void
    {
        $dir = __DIR__ . '/Discovery/RateLimited';
        $container = ContainerFactory::create(<<<NEON
            api:
                rateLimiting: {storage: memory}
                rateLimits:
                    perIp: {limit: 10, interval: 60}
                    named: {algorithm: fixedWindow, limit: 5, interval: 60, name: five}
                discovery: {paths: [$dir]}
                defaults: {authorization: Tomaj\NetteApi\Test\DI\Fixtures\IdentityAuthorization}
            NEON);
        $apis = $container->getByType(ApiDecider::class)->getApis();
        $limits = [];
        foreach ($apis as $api) {
            $limits[$api->getEndpoint()->getMethod() . ' ' . $api->getEndpoint()->getUrl()] = $api->getRateLimit();
        }
        ksort($limits);
        $this->assertSame(['GET v1/list/', 'GET v1/quota/', 'POST v1/quota/create'], array_keys($limits));

        $list = $limits['GET v1/list/'];
        $this->assertInstanceOf(RateLimiterChain::class, $list);
        $this->assertSame([$container->getService('api.rateLimit.perIp'), $container->getService('api.rateLimit.named')], $list->getLimiters());

        $get = $limits['GET v1/quota/'];
        $create = $limits['POST v1/quota/create'];
        $this->assertInstanceOf(TokenBucketLimiter::class, $get);
        $this->assertInstanceOf(TokenBucketLimiter::class, $create);
        $this->assertNotSame($get, $create, 'each endpoint of the class has its own limiter');
        $this->assertSame(['GET.v1.quota', 1000, 3600], [$get->getPolicy(), $get->getLimit(), $get->getInterval()]);
        $this->assertSame('POST.v1.quota.create', $create->getPolicy());
        $this->assertInstanceOf(CompositeKey::class, $get->getKey());
    }

    public function testAttributeArguments(): void
    {
        $this->assertSame(
            ['algorithm' => 'slidingWindow', 'limit' => 60, 'interval' => '60', 'key' => 'ip', 'name' => null],
            new RateLimit(limit: 60, interval: 60)->rateLimit,
        );
        $this->assertSame(
            ['algorithm' => 'tokenBucket', 'limit' => 60, 'interval' => '60', 'key' => 'identity', 'name' => 'quota'],
            new RateLimit(limit: 60, interval: 60, algorithm: 'tokenBucket', key: 'identity', name: 'quota')->rateLimit,
        );
        $this->assertSame('perIp', new RateLimit('perIp')->rateLimit);
        $this->assertFalse(new RateLimit(false)->rateLimit);
        try {
            new RateLimit('perIp', limit: 5);
            $this->fail('both a name and a limit');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame("#[RateLimit] has both a rate limit name and options of an inline limit (limit, interval, algorithm, key, name), which would be ignored. Use either #[RateLimit('perIp')] or #[RateLimit(limit: 60, interval: '1 minute')].", $e->getMessage());
        }
        foreach ([['interval' => '1 minute'], ['algorithm' => 'tokenBucket'], ['key' => 'identity'], ['name' => 'other']] as $option) {
            try {
                new RateLimit('perIp', ...$option);
                $this->fail('the option would be ignored: ' . key($option));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('which would be ignored', $e->getMessage());
            }
        }
        foreach ([[], ['limit' => 5]] as $arguments) {
            try {
                new RateLimit(...$arguments);
                $this->fail('no limit');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame("#[RateLimit] needs a name from api.rateLimits or limit and interval, for example #[RateLimit('perIp')] or #[RateLimit(limit: 60, interval: '1 minute')].", $e->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errors(): iterable
    {
        $where = 'the rateLimit of endpoint GET /v1/users (api.endpoints[0])';
        $endpoint = "    endpoints:\n        - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: %s}";
        $identity = "$where uses %s keyed by identity, but its authorization %s does not implement Tomaj\\NetteApi\\Authorization\\AuthorizationIdentityInterface, so the limit would never apply. Use key: ip, or give the endpoint an authorization that implements it (for example BearerTokenAuthorization).";
        $byIdentity = "    rateLimits:\n        perIp: {limit: 1, interval: 1}\n        own: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\FixedRateLimit(7)\n        perUser: {limit: 1, interval: 1, key: [endpoint, identity]}\n    authorizations:\n        none: Tomaj\\NetteApi\\Authorization\\NoAuthorization\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: {handler}, %s}";
        $none = NoAuthorization::class;
        yield 'identity without an authorization' => [
            sprintf($endpoint, '{limit: 1, interval: 1, key: identity}'),
            sprintf($identity, 'an inline limit', $none),
        ];
        yield 'identity key class without an authorization' => [
            sprintf($endpoint, '{limit: 1, interval: 1, key: Tomaj\\NetteApi\\RateLimit\\Key\\IdentityKey}'),
            sprintf($identity, 'an inline limit', $none),
        ];
        yield 'identity in a list with authorization false' => [
            sprintf($byIdentity, 'authorization: false, rateLimit: [perIp, own, perUser]'),
            sprintf($identity, 'api.rateLimits.perUser', $none),
        ];
        yield 'identity with a named authorization' => [
            sprintf($byIdentity, 'authorization: none, rateLimit: perUser'),
            sprintf($identity, 'api.rateLimits.perUser', $none),
        ];
        yield 'identity with a default authorization' => [
            "    defaults: {authorization: Tomaj\\NetteApi\\Authorization\\NoAuthorization()}\n" . sprintf($endpoint, '{limit: 1, interval: 1, key: identity}'),
            sprintf($identity, 'an inline limit', $none),
        ];
        yield 'algorithm' => [
            sprintf($endpoint, '{algorithm: slidingWindw, limit: 1, interval: 1}'),
            "$where has an unknown algorithm 'slidingWindw'. Did you mean 'slidingWindow'? Use one of 'fixedWindow', 'slidingWindow', 'tokenBucket', or a class implementing Tomaj\\NetteApi\\RateLimit\\Algorithm\\RateLimitAlgorithmInterface.",
        ];
        yield 'algorithm not implementing the interface' => [
            sprintf($endpoint, '{algorithm: stdClass, limit: 1, interval: 1}'),
            "The rate limit algorithm stdClass in $where does not implement Tomaj\\NetteApi\\RateLimit\\Algorithm\\RateLimitAlgorithmInterface. Implement step(), and also Tomaj\\NetteApi\\RateLimit\\Algorithm\\RedisScriptAlgorithmInterface to use it with Redis.",
        ];
        yield 'algorithm without script in redis' => [
            "    rateLimiting: {storage: redis, redis: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\FakeRedisClient}\n" . sprintf($endpoint, '{algorithm: Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp, limit: 1, interval: 1}'),
            "The rate limit algorithm Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp in $where cannot run in Redis (api.rateLimiting.storage: redis), which needs the decision as one atomic Lua script. Implement Tomaj\\NetteApi\\RateLimit\\Algorithm\\RedisScriptAlgorithmInterface in it, or use another storage.",
        ];
        $redisError = "The rate limit algorithm Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp in $where cannot run in Redis (api.rateLimiting.storage: %s), which needs the decision as one atomic Lua script.";
        yield 'algorithm statement without script in redis' => [
            "    rateLimiting: {storage: redis, redis: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\FakeRedisClient}\n" . sprintf($endpoint, '{algorithm: Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp(), limit: 1, interval: 1}'),
            sprintf($redisError, 'redis'),
        ];
        yield 'algorithm with a leading backslash without script in redis' => [
            "    rateLimiting: {storage: redis, redis: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\FakeRedisClient}\n" . sprintf($endpoint, '{algorithm: \\Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp(), limit: 1, interval: 1}'),
            sprintf($redisError, 'redis'),
        ];
        yield 'algorithm without script in a RedisStorage class' => [
            "    rateLimiting: {storage: Tomaj\\NetteApi\\RateLimit\\Storage\\RedisStorage}\n" . sprintf($endpoint, '{algorithm: Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp, limit: 1, interval: 1}'),
            sprintf($redisError, RedisStorage::class),
        ];
        yield 'algorithm without script in a RedisStorage statement' => [
            "    rateLimiting: {storage: Tomaj\\NetteApi\\RateLimit\\Storage\\RedisStorage(Tomaj\\NetteApi\\Test\\DI\\Fixtures\\FakeRedisClient())}\n" . sprintf($endpoint, '{algorithm: Tomaj\\NetteApi\\Test\\RateLimit\\Algorithm\\OnlyInPhp, limit: 1, interval: 1}'),
            sprintf($redisError, 'Tomaj\\NetteApi\\RateLimit\\Storage\\RedisStorage(...)'),
        ];
        yield 'interval' => [
            sprintf($endpoint, '{limit: 1, interval: 1 week}'),
            "SlidingWindowLimiter 'GET.v1.users': '1 week' is not an interval. Use a number of seconds (60)",
        ];
        yield 'limit' => [
            sprintf($endpoint, '{limit: 0, interval: 1 minute}'),
            "the limit 0 is less than 1.",
        ];
        yield 'key' => [
            sprintf($endpoint, '{limit: 1, interval: 1 minute, key: user}'),
            "$where: unknown key 'user'.",
        ];
        yield 'name' => [
            sprintf($endpoint, '{limit: 1, interval: 1 minute, name: "per ip"}'),
            "the policy name 'per ip' is not valid",
        ];
        yield 'two named limits with one policy name' => [
            "    rateLimits:\n        a: {limit: 1, interval: 1, name: same}\n        b: {limit: 2, interval: 1, name: same}",
            "api.rateLimits.a and api.rateLimits.b both use the policy name 'same', so they would share their counters. Give each one its own name.",
        ];
        yield 'a named limit with the name of another one' => [
            "    rateLimits:\n        perIp: {limit: 1, interval: 1}\n        b: {limit: 2, interval: 1, name: perIp}",
            "api.rateLimits.perIp and api.rateLimits.b both use the policy name 'perIp'",
        ];
        yield 'an inline limit with the name of a named one' => [
            "    rateLimits:\n        perIp: {limit: 1, interval: 1}\n" . sprintf($endpoint, '{limit: 1, interval: 1, name: perIp}'),
            "$where uses the policy name 'perIp' of api.rateLimits.perIp. Give the inline limit its own name, or use rateLimit: perIp to share the counters.",
        ];
        yield 'empty list' => [
            sprintf($endpoint, '[]'),
            'is missing. A rate limit needs limit and interval, for example {limit: 60, interval: 1 minute}.',
        ];
        yield 'unknown name in list' => [
            sprintf($endpoint, '[perIpp]'),
            "Unknown rateLimit 'perIpp' in $where[0]",
        ];
        yield 'unknown algorithm service' => [
            sprintf($endpoint, '{algorithm: @nope, limit: 1, interval: 1}'),
            "Service 'nope' used as @nope in $where.algorithm does not exist.",
        ];
        yield 'redis without client' => [
            "    rateLimiting: {storage: redis}\n" . sprintf($endpoint, '{limit: 1, interval: 1}'),
            'api.rateLimiting.storage is redis, but api.rateLimiting.redis is not set.',
        ];
        yield 'unknown storage' => [
            "    rateLimiting: {storage: rediss}\n" . sprintf($endpoint, '{limit: 1, interval: 1}'),
            "api.rateLimiting.storage 'rediss' is not known. Did you mean 'redis'? Use cache (Nette cache, the default, atomic only on one server), memory (one PHP process only, for tests), redis (atomic, needs rateLimiting.redis), @service or a class implementing Tomaj\\NetteApi\\RateLimit\\Storage\\RateLimitStorageInterface.",
        ];
        yield 'no cache' => [
            sprintf($endpoint, '{limit: 1, interval: 1}'),
            'The rate limits in api.rateLimits use api.rateLimiting.storage cache (the default), but no Nette\Caching\Storage service exists.',
        ];
    }

    public function testPolicyNamesDoNotMeetTheStorageServices(): void
    {
        $container = ContainerFactory::create(self::neon(<<<'NEON'
            api:
                rateLimiting: {storage: memory}
                rateLimits:
                    storage: {limit: 1, interval: 60}
                    storageFactory: {limit: 2, interval: 60}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: storage}
                    - {method: GET, version: 1, package: orders, handler: {handler}, rateLimit: storageFactory}
            NEON));
        [$first, $second] = $this->rateLimits($container);
        $this->assertTrue($first->consume(ContextFactory::create())[0]->accepted);
        $this->assertFalse($first->consume(ContextFactory::create())[0]->accepted);
        $this->assertSame(1, $second->consume(ContextFactory::create())[0]->remaining);
    }

    public function testAutowiredLegacyLimiterStillReachesAddApi(): void
    {
        $container = ContainerFactory::create(self::neon(<<<'NEON'
            services:
                - Tomaj\NetteApi\Test\DI\Fixtures\LegacyRateLimit
                decider:
                    factory: Tomaj\NetteApi\ApiDecider
                    setup:
                        - addApi(Tomaj\NetteApi\EndpointIdentifier(GET, 1, users), {handler}(), Tomaj\NetteApi\Authorization\NoAuthorization())
                        - addApi(Tomaj\NetteApi\EndpointIdentifier(GET, 1, orders), {handler}(), Tomaj\NetteApi\Authorization\NoAuthorization(), null)
            api: []
            NEON));
        $decider = $container->getService('decider');
        $this->assertInstanceOf(ApiDecider::class, $decider);
        [$autowired, $none] = @$decider->getApis();
        $this->assertInstanceOf(LegacyRateLimitAdapter::class, $autowired->getRateLimit());
        $this->assertInstanceOf(NoRateLimit::class, $none->getRateLimit());
    }

    public function testLoggerOfTheWarnings(): void
    {
        $neon = <<<'NEON'
            services:
                first: {factory: Tomaj\NetteApi\Test\DI\Fixtures\NullLogger}
                second: {factory: Tomaj\NetteApi\Test\DI\Fixtures\NullLogger}
            api:
                rateLimiting: {storage: memory%s}
                rateLimits:
                    perIp: {limit: 1, interval: 60}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: perIp}
            NEON;
        $limiter = $this->rateLimits(ContainerFactory::create(self::neon(sprintf($neon, ''))))[0];
        $this->assertNull(new ReflectionProperty(StorageRateLimiter::class, 'logger')->getValue($limiter), 'more ILogger services do not fail the compilation, Tracy logs');
        $container = ContainerFactory::create(self::neon(sprintf($neon, ', logger: @second')));
        $this->assertSame($container->getService('second'), new ReflectionProperty(StorageRateLimiter::class, 'logger')->getValue($this->rateLimits($container)[0]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function wrongRedisClients(): iterable
    {
        yield 'a service of another type' => ['@notRedis', "api.rateLimiting.redis @notRedis is stdClass, which is not a Redis client."];
        yield 'a class of another type' => ['stdClass', "api.rateLimiting.redis stdClass is stdClass, which is not a Redis client."];
        yield 'a statement of another type' => ['stdClass()', "api.rateLimiting.redis stdClass(...) is stdClass, which is not a Redis client."];
        yield 'a service without @' => ['notRedis', "api.rateLimiting.redis 'notRedis' is not a @service nor a class, for a service write @notRedis."];
        yield 'a class in the wrong case, a service name without @' => ['stdclass', "api.rateLimiting.redis 'stdclass' is not a @service nor a class, for a service write @stdclass."];
    }

    #[DataProvider('wrongRedisClients')]
    public function testWrongRedisClientFailsTheCompilation(string $client, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile(self::neon(sprintf(<<<'NEON'
            services:
                notRedis: stdClass
            api:
                rateLimiting: {storage: redis, redis: %s}
                rateLimits:
                    perIp: {limit: 1, interval: 60}
            NEON, $client)));
    }

    public function testRedisClientClassWithALeadingBackslash(): void
    {
        $container = ContainerFactory::create(self::neon(<<<'NEON'
            api:
                rateLimiting: {storage: redis, redis: \Tomaj\NetteApi\Test\DI\Fixtures\FakeRedisClient}
                rateLimits:
                    perIp: {limit: 1, interval: 60}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: perIp}
            NEON));
        $this->assertInstanceOf(StorageRateLimiter::class, $this->rateLimits($container)[0]);
    }

    public function testKeyClassThatCannotBeCreatedFailsTheCompilation(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the key class Tomaj\NetteApi\Test\RateLimit\Key\KeyWithArgument cannot be created without constructor arguments');
        ContainerFactory::compile(self::neon(<<<'NEON'
            api:
                rateLimiting: {storage: memory}
                rateLimits:
                    perTenant: {limit: 1, interval: 60, key: Tomaj\NetteApi\Test\RateLimit\Key\KeyWithArgument}
            NEON));
    }

    public function testRedisNeedsALuaScriptAlsoWithServices(): void
    {
        $neon = <<<'NEON'
            services:
                fakeRedis: Tomaj\NetteApi\Test\DI\Fixtures\FakeRedisClient
                redisStorage: Tomaj\NetteApi\RateLimit\Storage\RedisStorage(@fakeRedis)
                onlyInPhp: Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp
                lua: Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow
                memoryStorage: Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage
                redisStorageFactory: {factory: Tomaj\NetteApi\RateLimit\Storage\RedisStorage(@fakeRedis)}
                onlyInPhpFactory: {factory: Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp}
            api:
                rateLimiting: {storage: %s}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: {handler}, rateLimit: {algorithm: %s, limit: 1, interval: 1}}
            NEON;
        ContainerFactory::compile(self::neon(sprintf($neon, '@memoryStorage', '@onlyInPhpFactory')));
        $named = <<<'NEON'
            services:
                fakeRedis: Tomaj\NetteApi\Test\DI\Fixtures\FakeRedisClient
                redisStorage: Tomaj\NetteApi\RateLimit\Storage\RedisStorage(@fakeRedis)
                onlyInPhp: Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp
            api:
                rateLimiting: {storage: @redisStorage}
                rateLimits:
                    steady: {algorithm: @onlyInPhp, limit: 1, interval: 1}
            NEON;
        try {
            ContainerFactory::compile($named);
            $this->fail('a named policy is checked too');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString('in api.rateLimits.steady cannot run in Redis', $e->getMessage());
        }
        foreach ([['@redisStorageFactory', '@onlyInPhp'], ['@redisStorage', '@onlyInPhpFactory'], ['@redisStorageFactory', '@onlyInPhpFactory']] as [$storage, $algorithm]) {
            try {
                ContainerFactory::compile(self::neon(sprintf($neon, $storage, $algorithm)));
                $this->fail("$storage with $algorithm should fail the compilation");
            } catch (InvalidConfigurationException $e) {
                $this->assertStringContainsString('cannot run in Redis', $e->getMessage());
            }
        }
        foreach ([['@redisStorage', '@onlyInPhp'], ['memory', '@onlyInPhp'], ['@memoryStorage', '@onlyInPhp'], ['@memoryStorage', 'Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp()'], ['@redisStorage', '@lua'], ['@redisStorage', 'Tomaj\NetteApi\RateLimit\Algorithm\TokenBucket()']] as [$storage, $algorithm]) {
            try {
                ContainerFactory::compile(self::neon(sprintf($neon, $storage, $algorithm)));
                $this->assertNotSame(['@redisStorage', '@onlyInPhp'], [$storage, $algorithm], 'the compilation should fail');
            } catch (InvalidConfigurationException $e) {
                $this->assertSame(['@redisStorage', '@onlyInPhp'], [$storage, $algorithm], $e->getMessage());
                $this->assertStringContainsString('The rate limit algorithm Tomaj\NetteApi\Test\RateLimit\Algorithm\OnlyInPhp in the rateLimit of endpoint GET /v1/users (api.endpoints[0]) cannot run in Redis (api.rateLimiting.storage: @redisStorage)', $e->getMessage());
            }
        }
    }

    #[DataProvider('errors')]
    public function testErrors(string $api, string $message): void
    {
        try {
            ContainerFactory::compile(self::neon("api:\n" . $api));
            $this->fail('the compilation should fail');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
