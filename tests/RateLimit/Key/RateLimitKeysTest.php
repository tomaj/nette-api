<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Key;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\RateLimit\Key\CompositeKey;
use Tomaj\NetteApi\RateLimit\Key\EndpointKey;
use Tomaj\NetteApi\RateLimit\Key\IdentityKey;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeys;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\Test\RateLimit\ContextFactory;

final class RateLimitKeysTest extends TestCase
{
    public function testIpKey(): void
    {
        $key = new IpKey();
        $this->assertFalse($key->needsIdentity());
        $this->assertSame('ip:1.2.3.4', $key->key(ContextFactory::create()));
        $this->assertNull($key->key(ContextFactory::create(ip: null)));
        $this->assertNull($key->key(ContextFactory::create(ip: '')));
        $ipv6 = 'ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.255';
        $this->assertSame(45, strlen($ipv6));
        $this->assertSame('ip:ffff:ffff:ffff:ffff::/64', $key->key(ContextFactory::create(ip: $ipv6)), 'the longest IP is not hashed');
        $long = $ipv6 . ', 10.0.0.1';
        $this->assertSame('ip:' . hash('sha256', $long), $key->key(ContextFactory::create(ip: $long)), 'a longer value is hashed');
    }

    public function testIdentityKey(): void
    {
        $key = new IdentityKey();
        $this->assertTrue($key->needsIdentity());
        $this->assertSame('identity:' . hash('sha256', 'secret-token'), $key->key(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'secret-token')));
        $this->assertNull($key->key(ContextFactory::create(RateLimitStage::AfterAuthorization)));
    }

    public function testEndpointKey(): void
    {
        $key = new EndpointKey();
        $this->assertFalse($key->needsIdentity());
        $this->assertSame('endpoint:GET:2:users:list', $key->key(ContextFactory::create(ip: null)));
        $this->assertSame('endpoint:GET:2:users:', $key->key(ContextFactory::create(action: null)));
        $this->assertSame('endpoint:GET:2:users:a%3Ab%2F%C3%A1', $key->key(ContextFactory::create(action: 'a:b/á')), 'a : in the action cannot look like another endpoint');
    }

    public function testCompositeKey(): void
    {
        $key = new CompositeKey(new IpKey(), new EndpointKey());
        $this->assertFalse($key->needsIdentity());
        $this->assertSame('ip:1.2.3.4|endpoint:GET:2:users:list', $key->key(ContextFactory::create()));
        $this->assertNull($key->key(ContextFactory::create(ip: null)), 'a part without a key skips the limit');

        $key = new CompositeKey(new EndpointKey(), new IdentityKey());
        $this->assertTrue($key->needsIdentity());
        $this->assertNull($key->key(ContextFactory::create(RateLimitStage::AfterAuthorization)));
    }

    private static function fixed(string $value): RateLimitKeyInterface
    {
        return new readonly class ($value) implements RateLimitKeyInterface {
            public function __construct(private string $value)
            {
            }

            public function key(RateLimitContext $context): string
            {
                return $this->value;
            }

            public function needsIdentity(): bool
            {
                return false;
            }
        };
    }

    /**
     * @param list<list<string>> $lists
     */
    #[DataProvider('ambiguousParts')]
    public function testCompositeKeyPartsCannotCollide(array $lists, string $first): void
    {
        $keys = [];
        foreach ($lists as $parts) {
            $keys[] = new CompositeKey(...array_map(self::fixed(...), $parts))->key(ContextFactory::create());
        }
        $this->assertSame($first, $keys[0]);
        $this->assertSame($keys, array_unique($keys), 'every list of parts has its own key');
    }

    /**
     * @return iterable<array{list<list<string>>, string}>
     */
    public static function ambiguousParts(): iterable
    {
        yield 'separator' => [[['a|b', 'c'], ['a', 'b|c'], ['a', 'b', 'c']], 'a\\|b|c'];
        yield 'escape' => [[['a\\', 'b'], ['a\\|b'], ['a', '\\b']], 'a\\\\|b'];
        yield 'plain' => [[['ip:1.2.3.4', 'endpoint:GET:2:users:a%3Ab']], 'ip:1.2.3.4|endpoint:GET:2:users:a%3Ab'];
    }

    public function testEmptyCompositeKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CompositeKey needs at least one key, for example new CompositeKey(new IdentityKey(), new EndpointKey()).');
        new CompositeKey();
    }

    public function testFromName(): void
    {
        $this->assertInstanceOf(IpKey::class, RateLimitKeys::fromName('ip'));
        $this->assertInstanceOf(IdentityKey::class, RateLimitKeys::fromName('identity'));
        $this->assertInstanceOf(EndpointKey::class, RateLimitKeys::fromName(['endpoint']));
        $this->assertInstanceOf(IpKey::class, RateLimitKeys::fromName(IpKey::class));
        $this->assertInstanceOf(OwnKey::class, RateLimitKeys::fromName(OwnKey::class));
        foreach ([KeyWithArgument::class, RateLimitKeyInterface::class] as $class) {
            try {
                RateLimitKeys::fromName($class, 'rateLimits.perTenant.key');
                $this->fail('it cannot be created');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringStartsWith("rateLimits.perTenant.key: the key class $class cannot be created without constructor arguments: ", $e->getMessage());
                $this->assertInstanceOf(\Error::class, $e->getPrevious());
            }
        }

        $key = RateLimitKeys::fromName(['identity', 'endpoint', OwnKey::class]);
        $this->assertInstanceOf(CompositeKey::class, $key);
        $this->assertSame('identity:' . hash('sha256', 'u') . '|endpoint:GET:2:users:list|own', $key->key(ContextFactory::create(RateLimitStage::AfterAuthorization, identity: 'u')));
    }

    /**
     * @return iterable<array{string|array<mixed>, string}>
     */
    public static function invalid(): iterable
    {
        $hint = 'Use ip (one quota per client IP), identity (per caller, after the authorization), endpoint (one quota per endpoint shared by all callers), or a list like [identity, endpoint]; for your own key use a class implementing Tomaj\NetteApi\RateLimit\Key\RateLimitKeyInterface (without constructor arguments).';
        yield ['user', "the rate limit key: unknown key 'user'. " . $hint];
        yield [[\stdClass::class], "the rate limit key: unknown key 'stdClass'. " . $hint];
        yield [['ip', 5], "the rate limit key: unknown key '5'. " . $hint];
        yield [['ip', ['identity']], "the rate limit key: unknown key 'array'. " . $hint];
        yield [[], 'the rate limit key: the list of keys is empty. Use ip, identity, endpoint, or a list like [identity, endpoint].'];
    }

    /**
     * @param string|array<mixed> $key
     */
    #[DataProvider('invalid')]
    public function testInvalid(string|array $key, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        RateLimitKeys::fromName($key);
    }

    public function testWhere(): void
    {
        $this->expectExceptionMessage("rateLimits.perIp.key: unknown key 'ipp'.");
        RateLimitKeys::fromName('ipp', 'rateLimits.perIp.key');
    }

    public function testOwnKeyClassInAList(): void
    {
        $key = RateLimitKeys::fromName([OwnKey::class, 'ip']);
        $this->assertInstanceOf(CompositeKey::class, $key);
        $this->assertSame('own|ip:1.2.3.4', $key->key(ContextFactory::create()));
    }

    public function testCompositeKeyWithNamedArguments(): void
    {
        $key = new CompositeKey(...['first' => new IpKey(), 'second' => new EndpointKey()]);
        $this->assertSame(RateLimitKeys::fromName(['ip', 'endpoint'])->key(ContextFactory::create()), $key->key(ContextFactory::create()));
        $this->assertFalse($key->needsIdentity());
    }
}
