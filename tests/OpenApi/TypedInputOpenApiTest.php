<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;
use Tomaj\NetteApi\OpenApi\PathBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\OpenApi\SpecBuilder;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\HeaderInputParam;
use Tomaj\NetteApi\Params\PathInputParam;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\DocumentHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ConfigurableHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\TypedProfileHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\CasterInterface;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\DateHintHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\FloatRangeHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\NamedCaster;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;

final class TypedInputOpenApiTest extends TestCase
{
    public function testDtoOperationEqualsTheEquivalentParamsHandler(): void
    {
        $dto = $this->operation(new UserDetailHandler());
        $manual = $this->operation(new ConfigurableHandler([
            new PathInputParam('id')->setOpenApiSchema(['type' => 'integer', 'minimum' => 1]),
            new GetInputParam('limit')->setOpenApiSchema(['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]),
            new GetInputParam('status')->setOpenApiSchema(['type' => 'string', 'enum' => ['active', 'disabled']]),
            new HeaderInputParam('X-Request-Id')->setOpenApiSchema(['type' => 'string']),
        ]));

        $this->assertSame($manual['parameters'], $dto['parameters']);
        $this->assertMatchesSnapshot('typed-user-detail.json', $dto['parameters']);
    }

    public function testNestedBodyWithListOfObjectsIsDocumentedOnlyAsJson(): void
    {
        $dto = $this->operation(new CreateOrderHandler());
        $schema = $dto['requestBody']['content']['application/json']['schema'];
        $manual = $this->operation(new ConfigurableHandler([
            new BodyInputParam('customerEmail')->setRequired()->setOpenApiSchema($schema['properties']['customerEmail'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
            new BodyInputParam('address')->setRequired()->setOpenApiSchema($schema['properties']['address'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
            new BodyInputParam('items')->setRequired()->setOpenApiSchema($schema['properties']['items'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
            new BodyInputParam('deliveryAt')->setOpenApiSchema($schema['properties']['deliveryAt'])->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
        ]));

        $this->assertSame($manual['requestBody'], $dto['requestBody']);
        $this->assertSame(['customerEmail', 'address', 'items'], $schema['required']);
        $this->assertSame('email', $schema['properties']['customerEmail']['format']);
        $this->assertSame(1, $schema['properties']['items']['minItems']);
        // `items[0][sku]=...` has no serialization in OpenAPI 3.0, so the form is not documented
        $this->assertSame(['application/json'], array_keys($dto['requestBody']['content']));
        $this->assertMatchesSnapshot('typed-create-order.json', $dto['requestBody']);
    }

    public function testFormBodyUsesTheWireShapePhpParses(): void
    {
        $dto = $this->operation(new TypedProfileHandler());
        $multipart = $dto['requestBody']['content']['multipart/form-data'];

        $this->assertSame(['application/json', 'multipart/form-data'], array_keys($dto['requestBody']['content']));
        // OpenAPI sends an object part of multipart as JSON, so the fields of the address are listed under the names PHP parses
        $this->assertSame(['name', 'status', 'tags[]', 'address[street]', 'address[postalCode]', 'avatar'], array_keys($multipart['schema']['properties']));
        $this->assertSame(['name', 'tags[]', 'address[street]', 'address[postalCode]'], $multipart['schema']['required']);
        $this->assertArrayNotHasKey('encoding', $multipart);
        $this->assertSame(['active', 'disabled'], $multipart['schema']['properties']['status']['enum']);
        $this->assertArrayNotHasKey('nullable', $multipart['schema']['properties']['status']);
        $this->assertMatchesSnapshot('typed-profile.json', ['parameters' => $dto['parameters'], 'requestBody' => $dto['requestBody']]);
    }

    /**
     * No OpenAPI 3.0 meta schema is available offline, so the document is checked for the rules the typed input relies on.
     */
    public function testGeneratedDocumentWithTypedEndpointsIsWellFormed(): void
    {
        $apis = [
            new Api(new EndpointIdentifier('POST', '1', 'profile/{id}'), new TypedProfileHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('GET', '1', 'users/{id}/detail'), new UserDetailHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('POST', '1', 'orders'), new CreateOrderHandler(), new NoAuthorization()),
            new Api(new EndpointIdentifier('POST', '1', 'documents'), new DocumentHandler(), new NoAuthorization()),
        ];
        $spec = new SpecBuilder(new PathBuilder())->build($apis, '1', ApiFixtures::HOST_URL, ApiFixtures::apiLink());
        $json = json_encode($spec, JSON_THROW_ON_ERROR);
        $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($document);
        $this->assertIsString($document['openapi']);
        $this->assertStringStartsWith('3.0.', $document['openapi']);

        $forms = 0;
        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $operation) {
                foreach ($operation['parameters'] ?? [] as $parameter) {
                    $this->assertContains($parameter['in'], ['path', 'query', 'header', 'cookie']);
                    if ($parameter['in'] === 'path') {
                        $this->assertTrue($parameter['required']);
                        $this->assertStringContainsString('{' . $parameter['name'] . '}', (string) $path);
                    }
                    if ($parameter['in'] === 'query' && $parameter['schema']['type'] === 'array') {
                        $this->assertStringEndsWith('[]', $parameter['name']);
                    }
                }
                foreach ($operation['requestBody']['content'] ?? [] as $mediaType => $content) {
                    $schema = $content['schema'];
                    $this->assertSame('object', $schema['type']);
                    foreach ($schema['required'] ?? [] as $required) {
                        $this->assertArrayHasKey($required, $schema['properties']);
                    }
                    if ($mediaType === 'application/json') {
                        $this->assertArrayNotHasKey('encoding', $content);
                        continue;
                    }
                    $forms++;
                    $this->assertStringNotContainsString('"nullable"', json_encode($schema, JSON_THROW_ON_ERROR));
                    foreach ($schema['properties'] as $name => $property) {
                        if ($property['type'] === 'array') {
                            $this->assertStringEndsWith('[]', $name);
                            $this->assertNotContains($property['items']['type'], ['array', 'object'], 'no form serialization of nested lists');
                        }
                        if ($property['type'] === 'object') {
                            $this->assertSame(['style' => 'deepObject', 'explode' => true], $content['encoding'][$name]);
                        }
                    }
                    foreach (array_keys($content['encoding'] ?? []) as $encoded) {
                        $this->assertArrayHasKey($encoded, $schema['properties']);
                    }
                }
            }
        }
        $this->assertSame(2, $forms);

        // the JSON body schema is a JSON schema a validator accepts and applies (`nullable` is OpenAPI only, so null is not sent here)
        $profilePaths = array_values(array_filter(array_keys($document['paths']), static fn (string $path): bool => str_ends_with($path, '/profile/{id}')));
        $this->assertCount(1, $profilePaths);
        $schema = json_decode(json_encode($document['paths'][$profilePaths[0]]['post']['requestBody']['content']['application/json']['schema'], JSON_THROW_ON_ERROR));
        $valid = json_decode('{"name": "Ada", "status": "active", "tags": ["a"], "address": {"street": "Main", "postalCode": "81101"}}');
        $invalid = json_decode('{"name": "Ada", "tags": "a", "address": {"street": "", "postalCode": "811"}}');
        $validator = new Validator();
        $validator->validate($valid, $schema);
        $this->assertTrue($validator->isValid(), json_encode($validator->getErrors(), JSON_THROW_ON_ERROR));
        $validator = new Validator();
        $validator->validate($invalid, $schema);
        $this->assertCount(3, $validator->getErrors());
    }

    public function testDirectPathAndHeaderParamsUseThoseLocations(): void
    {
        $parameters = new ParameterBuilder()->build(new ConfigurableHandler([
            new PathInputParam('id'),
            new HeaderInputParam('X-Request-Id')->setDescription('Request id'),
            new GetInputParam('page'),
        ]));

        $this->assertSame('path', $parameters[0]['in']);
        $this->assertTrue($parameters[0]['required']);
        $this->assertSame(['type' => 'string'], $parameters[0]['schema']);
        $this->assertSame('header', $parameters[1]['in']);
        $this->assertSame('Request id', $parameters[1]['description']);
        $this->assertSame('query', $parameters[2]['in']);
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(BaseHandler $handler): array
    {
        return new PathBuilder()->buildOperation(
            new Api(new EndpointIdentifier('GET', 1, 'sample'), $handler, new NoAuthorization()),
            new SchemaRegistry(),
        );
    }

    /**
     * @param array<mixed> $value
     */
    private function assertMatchesSnapshot(string $name, array $value): void
    {
        $actual = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $file = __DIR__ . '/snapshots/' . $name;
        if (getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $actual);
            self::markTestIncomplete('Snapshot ' . $name . ' was written');
        }
        $this->assertFileExists($file);
        $this->assertSame(file_get_contents($file), $actual);
    }

    public function testANullableEnumListsNullInTheBodyAndNothingNullableOnTheWire(): void
    {
        $params = DtoCompiler::compile(LevelHandler::class)->inputParams();

        $this->assertSame(['type' => 'string', 'enum' => ['low', 'high', null], 'nullable' => true], $params[1]->getOpenApiSchema());
        $this->assertSame(['type' => 'string', 'enum' => ['low', 'high']], $params[2]->getOpenApiSchema());
        $this->assertSame([
            'type' => 'object',
            'properties' => ['text' => ['type' => 'string'], 'author' => ['type' => 'string', 'nullable' => true]],
            'required' => ['text'],
            'nullable' => true,
        ], $params[0]->getOpenApiSchema());
    }

    public function testDefaultsInTheSchema(): void
    {
        $fields = DtoCompiler::compile(SchemaDefaultsHandler::class)->fields;
        $this->assertSame('active', SchemaBuilder::build($fields[0], true)['default']);
        $this->assertSame('a,b', SchemaBuilder::build($fields[1], true)['default']);
        $this->assertArrayNotHasKey('default', SchemaBuilder::build($fields[2], true));
        $this->assertSame(2, SchemaBuilder::build($fields[3], true)['default']);
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

    public function testSchemaTypes(): void
    {
        $float = SchemaBuilder::build(DtoCompiler::compile(FloatRangeHandler::class)->fields[0], true);
        $this->assertSame('number', $float['type']);
        $bool = SchemaBuilder::build(DtoCompiler::compile(BoolHandler::class)->fields[0], true);
        $this->assertSame('boolean', $bool['type']);
        $date = SchemaBuilder::build(DtoCompiler::compile(DateHintHandler::class)->fields[0], true);
        $this->assertSame('string', $date['type']);
        $this->assertArrayNotHasKey('format', $date);
    }
}

final readonly class LevelNote
{
    public function __construct(#[Body] public string $text, #[Body] public ?string $author = null)
    {
    }
}

enum Level: string
{
    case Low = 'low';
    case High = 'high';
}

final readonly class LevelInput
{
    public function __construct(
        #[Body] public ?LevelNote $note = null,
        #[Body] public ?Level $level = null,
        #[Query] public ?Level $filter = null,
    ) {
    }
}

final class LevelHandler extends TypedHandler
{
    public function handleDto(LevelInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class StatusCaster implements CasterInterface
{
    public function cast(mixed $value): UserStatus
    {
        return UserStatus::from(is_string($value) ? $value : 'active');
    }
}

final readonly class SchemaDefaultsInput
{
    public function __construct(
        #[Query] public UserStatus $status = UserStatus::Active,
        #[Query] #[Cast(NamedCaster::class)] public string $text = 'a,b',
        #[Query] #[Cast(StatusCaster::class)] public UserStatus $casted = UserStatus::Active,
        #[Query] public Priority $priority = Priority::High,
    ) {
    }
}

final class SchemaDefaultsHandler extends TypedHandler
{
    public function handleDto(SchemaDefaultsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class BoolPinInput
{
    public function __construct(#[Query] public bool $flag = false)
    {
    }
}

final class BoolHandler extends TypedHandler
{
    public function handleDto(BoolPinInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
