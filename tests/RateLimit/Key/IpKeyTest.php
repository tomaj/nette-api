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

final class IpKeyTest extends TestCase
{
    public function testWithoutIpDetectorItNamesTheEndpoint(): void
    {
        $api = new Api(new EndpointIdentifier('post', 2, 'users', 'create'), new AlwaysOkHandler(), new NoAuthorization());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The rate limit of endpoint POST v2/users/create is keyed by ip');
        new IpKey()->key(new RateLimitContext($api, new Request('Api:Api:default'), RateLimitStage::BeforeAuthorization, null, hasIpDetector: false));
    }
}
