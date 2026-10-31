<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit\Key;

use Nette\Application\Request;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\RateLimit\Key\IpKey;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimitStage;
use Tomaj\NetteApi\Test\RateLimit\ContextFactory;

final class IpKeyTest extends TestCase
{
    public function testIpv6IsCountedPerSubnet(): void
    {
        $key = new IpKey();
        $first = $key->key(ContextFactory::create(ip: '2001:db8:1:2:aaaa::1'));
        $this->assertSame('ip:2001:db8:1:2::/64', $first);
        $this->assertSame($first, $key->key(ContextFactory::create(ip: '2001:db8:1:2:bbbb::2')), 'another address of the same subnet');
        $this->assertSame('ip:2001:db8:1:3::/64', $key->key(ContextFactory::create(ip: '2001:db8:1:3::1')));
    }

    public function testIpv4AndOtherValues(): void
    {
        $key = new IpKey();
        $this->assertSame('ip:1.2.3.4', $key->key(ContextFactory::create(ip: '1.2.3.4')));
        $this->assertSame('ip:1.2.3.4', $key->key(ContextFactory::create(ip: '::ffff:1.2.3.4')), 'an IPv4-mapped address is the IPv4 client');
        $this->assertSame('ip:::/64', $key->key(ContextFactory::create(ip: '::1')), 'zeros without ffff are not an IPv4-mapped address');
        $this->assertSame('ip:ffff::/64', $key->key(ContextFactory::create(ip: 'ffff::1')), 'ffff without zeros is not an IPv4-mapped address');
        $this->assertSame('ip:cli', $key->key(ContextFactory::create(ip: 'cli')), 'an own detector does not have to return an IP');
    }

    public function testWithoutIpDetectorItNamesTheEndpoint(): void
    {
        $api = new Api(new EndpointIdentifier('post', 2, 'users', 'create'), new AlwaysOkHandler(), new NoAuthorization());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The rate limit of endpoint POST v2/users/create is keyed by ip');
        new IpKey()->key(new RateLimitContext($api, new Request('Api:Api:default'), RateLimitStage::BeforeAuthorization, null, hasIpDetector: false));
    }
}
