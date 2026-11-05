<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Tomaj\NetteApi\Params\ParamsProcessor;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;

final class TypedInputExtensionTest extends TestCase
{
    public function testDiscoveryCompilesTypedHandlers(): void
    {
        $dir = dirname(__DIR__) . '/Input/Fixtures';
        $container = ContainerFactory::create(<<<NEON
api:
    discovery:
        paths: [$dir]
        namespace: Tomaj\NetteApi\Test\Input\Fixtures
NEON);

        $handlers = [];
        foreach ($container->getByType(ApiDecider::class)->getApis() as $api) {
            $handlers[$api->getEndpoint()->getUrl()] = $api->getHandler()::class;
        }
        $this->assertSame(ListUsersHandler::class, $handlers['v1/users/list']);

        RequestInput::reset();
        $_GET = ['page' => '3', 'status' => 'active'];
        $handler = $container->getByType(ListUsersHandler::class);
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError());
        $this->assertSame(3, $handler->handle($processor->getValues())->getPayload()['page']);
    }

    public function testABadDtoFailsContainerCompilation(): void
    {
        $dir = dirname(__DIR__) . '/DI/TypedBad';
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no contract to validate');
        ContainerFactory::create(<<<NEON
api:
    discovery:
        paths: [$dir]
        namespace: Tomaj\NetteApi\Test\DI\TypedBad
NEON);
    }

    public function testPathPlaceholderMustMatchTheDto(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('do not match');
        ContainerFactory::create(<<<'NEON'
api:
    endpoints:
        - {method: GET, version: 1, package: users, action: detail, handler: Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler}
NEON);
    }
}
