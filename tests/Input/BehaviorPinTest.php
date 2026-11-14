<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Input\DtoMetadata;
use Tomaj\NetteApi\Test\Input\Fixtures\AvatarHandler;
use Tomaj\NetteApi\DI\NetteApiExtension;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Consumes;
use Nette\Application\Request;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\ScalarCoercer;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\Input\ValueExport;
use Tomaj\NetteApi\OpenApi\RequestBodyBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Params\HeaderBag;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\DI\ContainerFactory;
use Tomaj\NetteApi\Test\Input\Fixtures\AddressInput;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

final class BehaviorPinTest extends TestCase
{
    protected function setUp(): void
    {
        RequestInput::reset();
        DtoMetadataRegistry::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    public function testCompiledOrderMatchesTheSnapshot(): void
    {
        $export = DtoCompiler::compile(CreateOrderHandler::class)->export();
        $files = $export['files'];
        unset($export['files']);
        $expected = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/create-order-export.json'), true);
        $this->assertSame($expected, $export);
        $this->assertTrue(array_is_list($files));
        $this->assertContains(realpath(__DIR__ . '/Fixtures/Handlers.php') ?: '', array_map(realpath(...), $files));
    }

    public function testImportedMetadataRoundTrips(): void
    {
        $compiled = DtoCompiler::compile(CreateOrderHandler::class);
        $export = $compiled->export();
        $export['contentTypes'][] = 5;
        $export['files'] = [2 => '/tmp/a.php', 4 => '/tmp/b.php'];
        $imported = DtoMetadata::import($export);
        $this->assertSame($compiled->handler, $imported->handler);
        $this->assertSame($compiled->class, $imported->class);
        $this->assertSame($compiled->contentTypes, $imported->contentTypes);
        $this->assertTrue(array_is_list($imported->files));
        $this->assertSame(['/tmp/a.php', '/tmp/b.php'], $imported->files);
        $again = $imported->export();
        unset($export['files'], $again['files'], $export['contentTypes'], $again['contentTypes']);
        $this->assertEquals($export, $again);

        $examples = DtoCompiler::compile(TwoExamplesHandler::class);
        $restored = DtoMetadata::import($examples->export());
        $this->assertSame(['one' => 1, 'two' => 2], $restored->fields[0]->examples);
        $this->assertSame([1, 2], DtoCompiler::compile(ListUsersHandler::class)->fields[3]->enumValues);
        $priority = DtoMetadata::import(DtoCompiler::compile(ListUsersHandler::class)->export());
        $this->assertSame([1, 2], $priority->fields[3]->enumValues);
    }

    public function testQueryOnlyHandlerDoesNotReadABody(): void
    {
        $metadata = DtoCompiler::compile(ListUsersHandler::class);
        $this->assertFalse($metadata->readsBody);
        $this->assertFalse($metadata->bodyRequired);
        $this->assertNull($metadata->firstBody);
        $this->assertFalse($metadata->fields[0]->listItemBuiltin);
        $this->assertFalse($metadata->fields[0]->enumInt);
        $this->assertTrue($metadata->fields[3]->enumInt);
        $this->assertSame([1, 2], $metadata->fields[3]->enumValues);
    }

    public function testSameInstanceIsReusedUntilReset(): void
    {
        $first = DtoMetadataRegistry::forHandler(ListUsersHandler::class);
        $again = DtoMetadataRegistry::forHandler(ListUsersHandler::class);
        $this->assertSame($first, $again);

        $export = $first->export();
        $export['firstBody'] = 'from-install';
        DtoMetadataRegistry::reset();
        DtoMetadataRegistry::install([ListUsersHandler::class => $export]);
        $this->assertTrue(DtoMetadataRegistry::installs(ListUsersHandler::class));
        $this->assertSame('from-install', DtoMetadataRegistry::forHandler(ListUsersHandler::class)->firstBody);
        $this->assertFalse(DtoMetadataRegistry::installs(CreateOrderHandler::class));
        $this->assertSame(CreateOrderHandler::class, DtoMetadataRegistry::forHandler(CreateOrderHandler::class)->handler);
    }

    public function testMissingHandlerNamesTheClass(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Typed handler 'Missing\\Handler' does not exist. Check the class name and the autoload section of composer.json.");
        DtoCompiler::compile('Missing\\Handler');
    }

    public function testCycleNamesEveryStep(): void
    {
        try {
            DtoCompiler::compile(CyclePinHandler::class);
            $this->fail('cycle');
        } catch (\LogicException $exception) {
            $this->assertSame(
                sprintf(
                    'DTO %s refers to itself (%s -> %s). Nested DTOs cannot form a cycle; keep the nesting one way.',
                    CyclePinInput::class,
                    CyclePinInput::class,
                    CyclePinInput::class,
                ),
                $exception->getMessage(),
            );
        }
    }

    public function testDeclarationsThatCannotBeMapped(): void
    {
        $cases = [
            PrivateHandler::class => 'must be a public instance method',
            StaticHandler::class => 'must be a public instance method',
            StdClassReturnHandler::class => 'must return',
            AbstractDtoHandler::class => 'cannot be an input DTO',
            EnumDtoHandler::class => 'cannot be an input DTO',
            InterfaceDtoHandler::class => 'cannot be an input DTO',
            IterableHandler::class => 'has type iterable',
            FileOnQueryHandler::class => 'needs #[File]',
            BareFileUploadHandler::class => 'needs #[File]',
        ];
        foreach ($cases as $handler => $needle) {
            try {
                DtoCompiler::compile($handler);
                $this->fail($handler);
            } catch (\LogicException $exception) {
                $this->assertStringContainsString($needle, $exception->getMessage(), $handler);
            }
        }

        $examples = DtoCompiler::compile(TwoExamplesHandler::class)->fields[0]->examples;
        $this->assertSame(['one' => 1, 'two' => 2], $examples);

        $twin = DtoCompiler::compile(TwinHandler::class);
        $this->assertSame('left', $twin->firstBody);
        $this->assertTrue($twin->readsBody);
        $address = realpath(new \ReflectionClass(AddressInput::class)->getFileName() ?: '');
        $this->assertCount(1, array_filter($twin->files, static fn (string $file): bool => realpath($file) === $address));
        $this->assertSame(['id', 'slug'], DtoCompiler::compile(TwoPathsHandler::class)->pathNames());
        $this->assertSame(
            [MediaTypes::JSON, MediaTypes::MULTIPART],
            DtoCompiler::compile(AvatarHandler::class)->contentTypes,
        );
        $described = DtoCompiler::compile(DescribedHandler::class);
        $this->assertFalse($described->bodyRequired);
        $this->assertTrue($described->readsBody);
        $this->assertSame([MediaTypes::JSON], $described->contentTypes);
    }

    public function testBoundsAcceptTheInclusiveEdge(): void
    {
        $range = new Range(min: 1, max: 1);
        $this->assertNull($range->validate(1, new ConstraintContext('n', 'Dto', ['n'], ['n' => 1])));
        $this->assertSame('1.5', new Range(min: 1.5, max: 2.5)->validate(1.5, new ConstraintContext('n', 'Dto', ['n'], ['n' => 1.5])) === null ? '1.5' : 'rejected');
        new Length(min: 2, max: 2);
        new Length(max: 0);
        $length = new Length(min: 0, max: 2);
        $this->assertNull($length->validate('', new ConstraintContext('s', 'Dto', ['s'], ['s' => ''])));
        $this->assertNull($length->validate('ab', new ConstraintContext('s', 'Dto', ['s'], ['s' => 'ab'])));
        $this->assertSame('Value must have between 0 and 2 characters', $length->validate('abc', new ConstraintContext('s', 'Dto', ['s'], ['s' => 'abc'])));
        $this->assertSame('Value must have at most 2 characters', new Length(max: 2)->validate('abc', new ConstraintContext('s', 'Dto', ['s'], ['s' => 'abc'])));
        $this->assertSame('^[0-9]{5}$', new Pattern('/^[0-9]{5}$/')->expression());
        try {
            new Length(max: -1);
            $this->fail('negative');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('zero or greater', $exception->getMessage());
        }
        $this->assertSame('Value must be at least 1.5', new Range(min: 1.5)->validate(1, new ConstraintContext('n', 'Dto', ['n'], ['n' => 1])));

        try {
            new ConstraintContext('page', 'Dto', ['page'], [])->value('missing');
            $this->fail('unknown');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("value('page')", $exception->getMessage());
        }
    }

    public function testDateAndEnumMessages(): void
    {
        $date = new DateFormat('d.m.Y')->cast('24.12.2026');
        $this->assertSame('2026-12-24T00:00:00+00:00', $date->format('c'));
        $parsed = ScalarCoercer::toDateTime('2026-12-24T00:00:00.1Z');
        $this->assertSame('100000', $parsed->format('u'));
        $longer = ScalarCoercer::toDateTime('2026-12-24T00:00:00.1234567Z');
        $this->assertSame('123456', $longer->format('u'));
        $this->assertSame('Value must be one of: 1, 2', ScalarCoercer::enumMessage(Priority::class));
        $this->assertSame(
            ['__netteApi' => 'enum', 'class' => Priority::class, 'value' => 1.5],
            ValueExport::import(['__netteApi' => 'enum', 'class' => Priority::class, 'value' => 1.5]),
        );
        $this->assertSame('application/json', MediaTypes::normalize(' Application/JSON ; charset=utf-8'));
    }

    public function testSchemaObjectCopiesDescription(): void
    {
        $field = DtoCompiler::compile(ListUsersHandler::class)->fields[0];
        $schema = SchemaBuilder::object([$field]);
        $this->assertSame('Page number', $schema['properties']['page']['description']);
        $this->assertSame('integer', $schema['properties']['page']['type']);
        $this->assertArrayNotHasKey('required', $schema);
        $id = DtoCompiler::compile(UserDetailHandler::class)->fields[0];
        $required = SchemaBuilder::object([$id]);
        $this->assertSame(['id'], $required['required']);
        $this->assertSame('integer', $required['properties']['id']['type']);
    }

    public function testHeadersIgnoreServerInternals(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'abc';
        $_SERVER['HTTP_X_TRACE'] = 'xyz';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertSame('abc', HeaderBag::get('x-request-id'));
        $this->assertSame('xyz', HeaderBag::get('X-Trace'));
        $this->assertFalse(HeaderBag::has('t-name'));
        $this->assertSame('application/json', HeaderBag::get('Content-Type'));
    }

    public function testFileUploadReplacesNonStrings(): void
    {
        $file = FileUpload::fromFile(['name' => 1, 'type' => 2, 'tmp_name' => 3, 'size' => '12']);
        $this->assertSame('', $file->originalName);
        $this->assertSame('', $file->clientContentType);
        $this->assertSame('', $file->temporaryPath);
        $this->assertSame(0, $file->size);
        $this->assertSame(\UPLOAD_ERR_NO_FILE, $file->error);
    }

    public function testNestedDefaultsRoundTrip(): void
    {
        $exported = ValueExport::export(['flag' => true, 'nested' => ['n' => 2], 'status' => Priority::High]);
        $this->assertSame(2, ValueExport::import($exported)['nested']['n']);
        $this->assertSame(Priority::High, ValueExport::import($exported)['status']);
    }

    public function testPathCaptureDoesNotLeakIntoTheNextEndpoint(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->enableGlobalPreflight();
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 2, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $matched = self::resolve($decider, 'GET', '1', 'users', '42', '/api/v1/users/42/detail');
        $id = null;
        foreach ($matched->getHandler()->params() as $param) {
            if ($param->getKey() === 'id') {
                $id = $param->getValue();
            }
        }
        $this->assertSame(42, $id);

        $static = self::resolve($decider, 'GET', '1', 'users', 'list', '/api/v1/users/list');
        $this->assertInstanceOf(AlwaysOkHandler::class, $static->getHandler());
        foreach (new UserDetailHandler()->params() as $param) {
            if ($param->getKey() === 'id') {
                $this->assertNull($param->getValue());
            }
        }

        $post = self::resolve($decider, 'POST', '1', 'users', '42', '/api/v1/users/42/detail');
        $this->assertInstanceOf(DefaultHandler::class, $post->getHandler());
        $withPreflight = self::resolve($decider, 'GET', '1', 'users', '7', '/api/v1/users/7/detail');
        $this->assertInstanceOf(UserDetailHandler::class, $withPreflight->getHandler());
        $otherVersion = self::resolve($decider, 'GET', '1', 'users', '7', '/api/v2/users/7/detail');
        $this->assertInstanceOf(DefaultHandler::class, $otherVersion->getHandler());

        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'detail'), new UserDetailHandler(), new NoAuthorization());
        $this->expectException(\LogicException::class);
        $decider->getApi('GET', '1', 'users', 'detail');
    }

    private static function resolve(ApiDecider $decider, string $method, string $version, string $package, string $apiAction, string $path): Api
    {
        $api = $decider->getApi($method, $version, $package, $apiAction);

        return $api->getHandler() instanceof DefaultHandler ? ($decider->matchPath($method, $version, $package, $apiAction, $path) ?? $api) : $api;
    }

    public function testPresenterDropsAStaleBody(): void
    {
        RequestInput::setRawBody('{"customerEmail":"ada@example.com","address":{"street":"Main","postalCode":"81101"},"items":[{"sku":"A","quantity":1}]}');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('POST', 1, 'orders'), new CreateOrderHandler(), new NoAuthorization());
        $presenter = new ApiPresenter();
        $presenter->apiDecider = $decider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);
        $result = $presenter->run(new Request('Api:Api:default', 'POST', ['version' => '1', 'package' => 'orders']));
        $this->assertSame(400, $result->getCode());
    }

    public function testASecondBootOfTheSameHandlerKeepsTheFirstRead(): void
    {
        $_GET = ['page' => '4'];
        $first = $this->payload(new ListUsersHandler());
        $_GET = ['page' => '9'];
        $second = $this->payload(new ListUsersHandler());
        $this->assertSame(4, $first['page']);
        $this->assertSame(4, $second['page']);

        RequestInput::reset();
        $_GET = ['page' => '9'];
        $this->assertSame(9, $this->payload(new ListUsersHandler())['page']);
    }

    public function testFieldErrorsStayOnTheirOwnName(): void
    {
        $params = new PrefixHandler()->params();
        $byName = [];
        foreach ($params as $param) {
            $result = $param->validate();
            $this->assertFalse($result->isOk());
            $byName[$param->getKey()] = $result->getErrors();
        }
        $this->assertSame(['a' => ['Field is required']], $byName['a']);
        $this->assertSame(['ab' => ['Field is required']], $byName['ab']);
        $this->assertSame('', $params[0]->getDescription());
        $page = new ListUsersHandler()->params()[0];
        $this->assertSame('Page number', $page->getDescription());
        $this->assertFalse($page->isMulti());
        $items = new CreateOrderHandler()->params()[2];
        $this->assertTrue($items->isMulti());
        $this->assertNotSame([], $items->getContentTypes());
    }

    public function testMalformedJsonStillChecksQueryFields(): void
    {
        $_GET = ['page' => '1a'];
        RequestInput::setRawBody('{"email":');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $errors = $this->errors(new QueryAfterBodyHandler());
        $this->assertStringStartsWith('Syntax error', $errors['body'][0]);
        $this->assertSame(['Value must be an integer'], $errors['page']);
    }

    public function testInvalidIntegerKeepsTheDefaultForTheValue(): void
    {
        $_GET = ['page' => '1a'];
        $page = new ListUsersHandler()->params()[0];
        $page->validate();
        $this->assertSame(1, $page->getValue());
    }

    public function testEveryNestedErrorIsReported(): void
    {
        $this->json([
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => '', 'postalCode' => 'abc'],
            'items' => [
                ['sku' => 'A', 'quantity' => 0],
                ['sku' => 'B', 'quantity' => 0],
            ],
        ]);
        $errors = $this->errors(new CreateOrderHandler());
        $this->assertArrayHasKey('address.street', $errors);
        $this->assertArrayHasKey('address.postalCode', $errors);
        $this->assertArrayHasKey('items[0].quantity', $errors);
        $this->assertArrayHasKey('items[1].quantity', $errors);

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"customerEmail":"ada@example.com","address":{},"items":[{"sku":"A","quantity":1}]}');
        $emptyObject = $this->errors(new CreateOrderHandler());
        $this->assertSame(['Field is required'], $emptyObject['address.street']);
        $this->assertArrayNotHasKey('address', $emptyObject);

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"customerEmail":"ada@example.com","address":[],"items":[{"sku":"A","quantity":1}]}');
        $this->assertSame(['Value must be an object'], $this->errors(new CreateOrderHandler())['address']);

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"customerEmail":"ada@example.com","address":{"street":"Main 1","postalCode":"81101"},"items":{}}');
        $this->assertSame(['Value must be a list'], $this->errors(new CreateOrderHandler())['items']);

        RequestInput::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('[]');
        $this->assertSame(['body' => ['Value must be an object']], $this->errors(new CreateOrderHandler()));
    }

    public function testEmptyObjectAndFloat(): void
    {
        RequestInput::setRawBody('{}');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $errors = $this->errors(new CreateOrderHandler());
        $this->assertArrayNotHasKey('body', $errors);
        $this->assertSame(['Field is required'], $errors['customerEmail']);

        RequestInput::reset();
        $_GET = ['ratio' => '1.5'];
        $this->assertEqualsWithDelta(1.5, $this->payload(new FloatHandler())['ratio'], PHP_FLOAT_EPSILON);
    }

    public function testUploadErrorShapes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
        $_FILES = ['avatar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0]];
        $_POST = ['displayName' => 'Ada'];
        $this->assertSame(['avatar' => ['Field is required']], $this->errors(new AvatarHandler()));

        RequestInput::reset();
        $_FILES = ['avatar' => ['name' => 'a.png', 'type' => '', 'tmp_name' => '/tmp/a', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 4]];
        $this->assertSame('a.png', $this->payload(new AvatarHandler())['file']);

        RequestInput::reset();
        $_FILES = ['avatar' => ['error' => \UPLOAD_ERR_NO_FILE, 'size' => 0]];
        $this->assertSame(['avatar' => ['Field is required']], $this->errors(new AvatarHandler()));
    }

    public function testBodyParamReadsJsonObjectsOnly(): void
    {
        RequestInput::setRawBody('{"note":"hi","1":"n"}');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $param = new BodyInputParam('note');
        $this->assertSame('hi', $param->getValue());
        $numeric = new BodyInputParam('1');
        $this->assertSame('n', $numeric->getValue());

        RequestInput::reset();
        RequestInput::setRawBody('[1, 2]');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertNull(new BodyInputParam('note')->getValue());

        RequestInput::reset();
        RequestInput::setRawBody('');
        $this->assertNull(new BodyInputParam('note')->getValue());
    }

    public function testOpenApiUsesTheDeclaredBodyMediaTypeAndDescription(): void
    {
        $builder = new RequestBodyBuilder();
        $body = $builder->build(new DescribedHandler(), new SchemaRegistry());
        $this->assertIsArray($body);
        $this->assertSame(['application/json'], array_keys($body['content']));
        $this->assertSame('Customer email', $body['content']['application/json']['schema']['properties']['email']['description']);
        $this->assertFalse($body['required']);
    }

    public function testLinkKeepsASlashInThePackage(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'acme/widgets', 'show'), ['q' => 'a b']);
        $this->assertSame('https://example.com/api/v1/acme/widgets/show?q=a+b', $link);
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
        $this->assertTrue(DtoMetadataRegistry::installed());
        $this->assertTrue(DtoMetadataRegistry::installs(ListUsersHandler::class));
        $this->assertTrue(DtoMetadataRegistry::installs(CreateOrderHandler::class));
        $urls = [];
        foreach ($container->getByType(ApiDecider::class)->getApis() as $api) {
            $urls[] = $api->getEndpoint()->getUrl();
        }
        $this->assertContains('v1/people/', $urls);
        $this->assertContains('v1/orders/', $urls);

        DtoMetadataRegistry::reset();
        $empty = ContainerFactory::create("api:\n    endpoints: []\n");
        $empty->initialize();
        $this->assertFalse(DtoMetadataRegistry::installed());

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
        $this->assertTrue(DtoMetadataRegistry::installs(ListUsersHandler::class));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): void
    {
        RequestInput::reset();
        RequestInput::setRawBody((string) json_encode($body));
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];
        $_POST = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));

        return $handler->handle($processor->getValues())->getPayload();
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(object $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
    }
}

final readonly class CyclePinInput
{
    public function __construct(#[Body] public CyclePinInput $child)
    {
    }
}

final class CyclePinHandler extends TypedHandler
{
    public function handleDto(CyclePinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class PrivateHandler extends TypedHandler
{
    private function handleDto(CyclePinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class StaticHandler extends TypedHandler
{
    public static function handleDto(CyclePinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class StdClassReturnHandler extends TypedHandler
{
    public function handleDto(CyclePinInput $input): \stdClass
    {
        return new \stdClass();
    }
}

abstract readonly class AbstractPinInput
{
    public function __construct(#[Query] public string $name)
    {
    }
}

final class AbstractDtoHandler extends TypedHandler
{
    public function handleDto(AbstractPinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class EnumDtoHandler extends TypedHandler
{
    public function handleDto(Priority $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

interface PinContract
{
}

final class InterfaceDtoHandler extends TypedHandler
{
    public function handleDto(PinContract $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class IterableInput
{
    public function __construct(#[Query] public iterable $name)
    {
    }
}

final class IterableHandler extends TypedHandler
{
    public function handleDto(IterableInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class FileOnQueryInput
{
    public function __construct(#[Query] public FileUpload $file)
    {
    }
}

final class FileOnQueryHandler extends TypedHandler
{
    public function handleDto(FileOnQueryInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BareFileUploadInput
{
    /** @param list<FileUpload> $files */
    public function __construct(#[Query] public array $files)
    {
    }
}

final class BareFileUploadHandler extends TypedHandler
{
    public function handleDto(BareFileUploadInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class TwoExamplesInput
{
    public function __construct(#[Query(examples: ['one' => 1, 'two' => 2])] public int $page = 1)
    {
    }
}

final class TwoExamplesHandler extends TypedHandler
{
    public function handleDto(TwoExamplesInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class TwinInput
{
    public function __construct(
        #[Body] public AddressInput $left,
        #[Body] public AddressInput $right,
    ) {
    }
}

final class TwinHandler extends TypedHandler
{
    public function handleDto(TwinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['street' => $input->left->street]);
    }
}

final readonly class TwoPathsInput
{
    public function __construct(
        #[Path] public string $id,
        #[Path] public string $slug,
    ) {
    }
}

final class TwoPathsHandler extends TypedHandler
{
    public function handleDto(TwoPathsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class PrefixInput
{
    public function __construct(
        #[Query(description: '')] public string $a,
        #[Query] public string $ab,
    ) {
    }
}

final class PrefixHandler extends TypedHandler
{
    public function handleDto(PrefixInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class QueryAfterBodyInput
{
    public function __construct(
        #[Body] public string $email,
        #[Query] public int $page = 1,
    ) {
    }
}

final class QueryAfterBodyHandler extends TypedHandler
{
    public function handleDto(QueryAfterBodyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['page' => $input->page]);
    }
}

final readonly class FloatInput
{
    public function __construct(#[Query] public float $ratio)
    {
    }
}

final class FloatHandler extends TypedHandler
{
    public function handleDto(FloatInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['ratio' => $input->ratio]);
    }
}

final readonly class DescribedInput
{
    public function __construct(
        #[Body(description: 'Customer email')] public ?string $email = null,
    ) {
    }
}

#[Consumes('application/json')]
final class DescribedHandler extends TypedHandler
{
    public function handleDto(DescribedInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
