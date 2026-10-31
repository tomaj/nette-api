<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\RateLimit;

use Nette\Application\Request;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\RateLimit\RateLimitContext;
use Tomaj\NetteApi\RateLimit\RateLimitStage;

final class ContextFactory
{
    public static function create(RateLimitStage $stage = RateLimitStage::BeforeAuthorization, ?string $ip = '1.2.3.4', ?string $identity = null, ?string $action = 'list'): RateLimitContext
    {
        $api = new Api(new EndpointIdentifier('get', 2, 'users', $action), new AlwaysOkHandler(), new NoAuthorization());
        return new RateLimitContext($api, new Request('Api:Api:default'), $stage, $ip, $identity);
    }
}
