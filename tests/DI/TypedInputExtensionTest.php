<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Nette\DI\Container;
use Tomaj\NetteApi\Params\ParamsProcessor;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ScheduleHandler;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;
use Nette\DI\Compiler;
use Tomaj\NetteApi\DI\NetteApiExtension;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteHandler;

final class TypedInputExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        DtoMetadataRegistry::reset();
    }

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

    public function testContainerInstallsSerializedMetadataAndUnpacksItOnFirstUse(): void
    {
        DtoMetadataRegistry::reset();
        $code = ContainerFactory::compile(<<<'NEON'
api:
    endpoints:
        - {method: GET, version: 1, package: people, handler: Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler}
        - {method: POST, version: 1, package: schedule, handler: Tomaj\NetteApi\Test\Input\Fixtures\ScheduleHandler}
NEON);
        $this->assertStringContainsString('DtoMetadataRegistry::install(', $code);
        $this->assertStringNotContainsString('Handlers.php', $code, 'compile-time file paths are not part of the runtime payload');

        preg_match('~class (ApiTestContainer\w+)~', $code, $match);
        eval($code);
        $container = new $match[1]();
        $this->assertInstanceOf(Container::class, $container);
        $container->initialize();
        $this->assertContains(ScheduleHandler::class, self::installedHandlers());
        $unpacked = new \ReflectionProperty(DtoMetadataRegistry::class, 'metadata');
        $this->assertSame([], $unpacked->getValue(), 'nothing is unserialized until a handler is used');

        // ScheduleHandler has a constraint with a private property, which serialize() writes with NUL bytes
        $this->assertEquals(DtoCompiler::compile(ScheduleHandler::class), DtoMetadataRegistry::forHandler(ScheduleHandler::class));
        $this->assertSame([ScheduleHandler::class], array_keys((array) $unpacked->getValue()));
        DtoMetadataRegistry::reset();
    }

    public function testAConstraintThatCannotBeSerializedFailsContainerCompilation(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf("The typed input of %s cannot be stored in the compiled container: Serialization of 'Closure' is not allowed.", ClosureConstraintHandler::class));
        ContainerFactory::compile(<<<'NEON'
api:
    endpoints:
        - {method: POST, version: 1, package: closure, handler: Tomaj\NetteApi\Test\DI\ClosureConstraintHandler}
NEON);
    }

    public function testExtensionInstallsTypedHandlersAndTheirFiles(): void
    {
        $container = ContainerFactory::create(<<<'NEON'
services:
    listUsers: Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler
api:
    endpoints:
        - {method: GET, version: 1, package: people, handler: @listUsers}
        - {method: POST, version: 1, package: orders, handler: Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler()}
NEON);
        $container->initialize();
        $this->assertNotSame([], self::installedHandlers());
        $this->assertContains(ListUsersHandler::class, self::installedHandlers());
        $this->assertContains(CreateOrderHandler::class, self::installedHandlers());
        $urls = [];
        foreach ($container->getByType(ApiDecider::class)->getApis() as $api) {
            $urls[] = $api->getEndpoint()->getUrl();
        }
        $this->assertContains('v1/people/', $urls);
        $this->assertContains('v1/orders/', $urls);

        DtoMetadataRegistry::reset();
        $empty = ContainerFactory::create("api:\n    endpoints: []\n");
        $empty->initialize();
        $this->assertSame([], self::installedHandlers());

        $compiler = new Compiler();
        $compiler->addExtension('api', new NetteApiExtension());
        $compiler->addConfig([
            'api' => [
                'endpoints' => [[
                    'method' => 'POST',
                    'version' => 1,
                    'package' => 'orders',
                    'handler' => CreateOrderHandler::class,
                ]],
            ],
        ]);
        $compiler->compile();
        $this->assertStringContainsString('Handlers.php', serialize($compiler->exportDependencies()));
    }

    public function testBackslashHandlerIsInstalledUnderItsRealName(): void
    {
        $container = ContainerFactory::create(<<<'NEON'
api:
    endpoints:
        - {method: GET, version: 1, package: people, action: list, handler: \Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler}
NEON);
        $container->initialize();
        $this->assertContains(ListUsersHandler::class, self::installedHandlers());
    }

    public function testContainerKeepsTypedHandlersAfterAPlainOneAndRejectsOverlap(): void
    {
        $container = ContainerFactory::create(<<<'NEON'
services:
    listUsers: Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler
api:
    endpoints:
        - {method: GET, version: 1, package: ping, handler: Tomaj\NetteApi\Handlers\AlwaysOkHandler}
        - {method: GET, version: 1, package: people, action: list, handler: @listUsers}
        - {method: POST, version: 1, package: notes, handler: \Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteHandler()}
NEON);
        $container->initialize();
        $this->assertContains(ListUsersHandler::class, self::installedHandlers());
        $this->assertContains(NoteHandler::class, self::installedHandlers());
        $this->assertInstanceOf(AlwaysOkHandler::class, $container->getByType(AlwaysOkHandler::class));

        $compiler = new Compiler();
        $compiler->addExtension('api', new NetteApiExtension());
        $compiler->addConfig([
            'api' => [
                'endpoints' => [[
                    'method' => 'POST',
                    'version' => 1,
                    'package' => 'notes',
                    'handler' => NoteHandler::class,
                ]],
            ],
        ]);
        $compiler->compile();
        $this->assertStringContainsString('NoteInput.php', serialize($compiler->exportDependencies()));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('can match the same request');
        ContainerFactory::create(<<<'NEON'
api:
    endpoints:
        - {method: GET, version: 1, package: users, action: '{id}', handler: Tomaj\NetteApi\Test\DI\IdOnlyHandler}
        - {method: GET, version: 1, package: users, action: '{slug}', handler: Tomaj\NetteApi\Test\DI\SlugOnlyHandler}
NEON);
    }

    /**
     * @return list<string>
     */
    private static function installedHandlers(): array
    {
        return array_keys((array) new \ReflectionProperty(DtoMetadataRegistry::class, 'installed')->getValue());
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ClosureConstraint implements ConstraintInterface
{
    private \Closure $check;

    public function __construct()
    {
        $this->check = static fn (mixed $value): bool => $value !== '';
    }

    #[\Override]
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        return ($this->check)($value) ? null : 'Value must not be empty';
    }
}

final readonly class ClosureConstraintInput
{
    public function __construct(#[Body] #[ClosureConstraint] public string $name)
    {
    }
}

final class ClosureConstraintHandler extends TypedHandler
{
    public function handleDto(ClosureConstraintInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class IdOnlyInput
{
    public function __construct(#[Path] public string $id)
    {
    }
}

final class IdOnlyHandler extends TypedHandler
{
    public function handleDto(IdOnlyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class SlugOnlyInput
{
    public function __construct(#[Path] public string $slug)
    {
    }
}

final class SlugOnlyHandler extends TypedHandler
{
    public function handleDto(SlugOnlyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
