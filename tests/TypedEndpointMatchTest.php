<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test;

use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;

final class TypedEndpointMatchTest extends TestCase
{
    public function testTemplateMatchWinsWhenTheRouteSplitsTheId(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $exact = $decider->getApi('GET', '1', 'users', 'list', '/api/v1/users/list');
        $this->assertInstanceOf(AlwaysOkHandler::class, $exact->getHandler());

        $matched = $decider->getApi('GET', '1', 'users', '42', '/api/v1/users/42/detail');
        $this->assertInstanceOf(UserDetailHandler::class, $matched->getHandler());
        $this->assertSame('v1/users/{id}/detail', $matched->getHandler()->getEndpoint()?->getUrl());

        $missing = $decider->getApi('GET', '1', 'users', '42', '/api/v1/users/42');
        $this->assertInstanceOf(DefaultHandler::class, $missing->getHandler());
    }

    public function testOverlappingTemplatesAreReported(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}'), new UserDetailHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{slug}'), new UserDetailHandler(), new NoAuthorization());

        $this->expectException(\LogicException::class);
        $decider->getApi('GET', '1', 'users', '42', '/api/v1/users/42');
    }

    public function testOptionsOnATemplateUsesThePreflightHandler(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->enableGlobalPreflight();
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $api = $decider->getApi('OPTIONS', '1', 'users', '42', '/api/v1/users/42/detail');
        $this->assertInstanceOf(CorsPreflightHandler::class, $api->getHandler());
    }

    public function testLinkKeepsPathPlaceholders(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), ['limit' => 10]);
        $this->assertStringContainsString('/api/v1/users/{id}/detail', $link);
        $this->assertStringContainsString('limit=10', $link);
    }

    public function testStaticLinkIsUnchanged(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', 'list'));
        $this->assertSame('https://example.com/api/v1/users/list', $link);
        $withQuery = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', 'list'), ['q' => '1']);
        $this->assertSame('https://example.com/api/v1/users/list?q=1', $withQuery);
        $placeholder = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', '{id}'));
        $this->assertStringContainsString('/api/v1/users/{id}', $placeholder);
    }
}
