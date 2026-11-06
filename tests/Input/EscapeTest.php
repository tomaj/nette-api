<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Validation\ConstraintInterface;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Query;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\OpenApi\RequestBodyBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\BodyInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Test\Input\Fixtures\AvatarHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Validation\Range;

final class EscapeTest extends TestCase
{
    protected function setUp(): void
    {
        RequestInput::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    public function testImportedFieldRejectsCorruptMetadata(): void
    {
        $export = DtoCompiler::compile(ListUsersHandler::class)->export();
        $field = $export['fields'][0];
        $field['casterArguments'] = [0 => 'd.m.Y', 2 => 'ignored'];
        $imported = Field::import($field);
        $this->assertTrue(array_is_list($imported->casterArguments));
        $this->assertCount(2, $imported->casterArguments);
        $this->assertSame('d.m.Y', $imported->casterArguments[0]);

        $field['enumClass'] = \stdClass::class;
        $this->expectException(\LogicException::class);
        Field::import($field);
    }

    public function testImportedFieldRejectsABadCasterAndConstraint(): void
    {
        $export = DtoCompiler::compile(ListUsersHandler::class)->export();
        $field = $export['fields'][0];
        $field['casterClass'] = \stdClass::class;
        try {
            Field::import($field);
            $this->fail('caster');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('caster', $exception->getMessage());
        }

        $field = $export['fields'][0];
        $field['property'] = '';
        try {
            Field::import($field);
            $this->fail('property');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('property', $exception->getMessage());
        }

        $field = $export['fields'][0];
        $field['constraintSpecs'] = [new FakeConstraintSpec()];
        try {
            Field::import($field);
            $this->fail('constraint shape');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('constraint', $exception->getMessage());
        }

        $field = $export['fields'][0];
        $field['constraintSpecs'] = [['class' => Range::class, 'args' => 'nope']];
        try {
            Field::import($field);
            $this->fail('constraint args');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('constraint', $exception->getMessage());
        }

        $field = $export['fields'][0];
        $field['constraintSpecs'] = [['class' => \stdClass::class, 'args' => []]];
        try {
            Field::import($field);
            $this->fail('constraint');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('constraint', $exception->getMessage());
        }

        $field = $export['fields'][3];
        $field['enumValues'] = [1, 1.5];
        try {
            Field::import($field);
            $this->fail('enum');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('enum values', $exception->getMessage());
        }
    }

    public function testRequiredHeaderAndPathAreMissingRatherThanNull(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $errors = $this->errors(new RequiredHeaderHandler());
        $this->assertSame(['Field is required'], $errors['trace']);

        $errors = $this->errors(new RequiredPathHandler());
        $this->assertSame(['Field is required'], $errors['id']);
    }

    public function testStringFileIsNotAMissingUpload(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => 'nope'];
        $this->assertSame(['Field is required'], $this->errors(new AvatarHandler())['avatar']);

        RequestInput::reset();
        $_FILES = ['avatar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_OK, 'size' => 1]];
        $this->assertSame('', $this->payload(new AvatarHandler())['file']);

        RequestInput::reset();
        $_FILES = ['avatar' => [
            'name' => ['a.png', 'b.png'],
            'type' => ['image/png', 'image/png'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
            'size' => [1, 2],
        ]];
        $this->assertSame(['Value must be a file upload'], $this->errors(new AvatarHandler())['avatar']);
    }

    public function testJsonContentTypeCanComeFromTheHttpHeader(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        RequestInput::setRawBody('{"customerEmail":"ada@example.com","address":{"street":"Main 1","postalCode":"81101"},"items":[{"sku":"A","quantity":1}]}');
        $this->assertSame('ada@example.com', $this->payload(new CreateOrderHandler())['email']);
    }

    public function testPostWithAnEmptySuperglobalDoesNotReadTheRawForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];
        RequestInput::setRawBody('customerEmail=ada%40example.com');
        $this->assertArrayHasKey('customerEmail', $this->errors(new CreateOrderHandler()));
    }

    public function testUnknownSourceAttributeIsRejected(): void
    {
        $compiler = new \ReflectionClass(DtoCompiler::class)->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DtoCompiler::class, 'sourceAttribute');
        $this->expectException(\LogicException::class);
        $method->invoke($compiler, new \stdClass());
    }

    public function testAMissingNestedFieldDoesNotHideTheNextError(): void
    {
        RequestInput::setRawBody('{"customerEmail":"ada@example.com","address":{"postalCode":"abc"},"items":[{"sku":"A","quantity":1}]}');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $errors = $this->errors(new CreateOrderHandler());
        $this->assertArrayHasKey('address.street', $errors);
        $this->assertArrayHasKey('address.postalCode', $errors);
    }

    public function testNestedConstraintCanReadASibling(): void
    {
        RequestInput::setRawBody('{"inner":{"left":"a","right":"b"}}');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertSame('b', $this->payload(new SiblingHandler())['right']);
    }

    public function testOpenApiKeepsQueryFieldsOutOfTheBody(): void
    {
        $body = new RequestBodyBuilder()->build(new OpenApiMixedHandler(), new SchemaRegistry());
        $this->assertIsArray($body);
        $this->assertSame(['email'], array_keys($body['content']['application/json']['schema']['properties']));
        $this->assertArrayHasKey('application/json', $body['content']);

        $loose = new RequestBodyBuilder()->build(new LooseBodyHandler(), new SchemaRegistry());
        $this->assertIsArray($loose);
        $this->assertSame(
            ['application/json', 'application/x-www-form-urlencoded'],
            array_keys($loose['content']),
        );
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

    /**
     * @return array<string, mixed>
     */
    private function payload(object $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));

        return $handler->handle($processor->getValues())->getPayload();
    }
}

final class RequiredHeaderHandler extends TypedHandler
{
    public function handleDto(RequiredHeaderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class RequiredHeaderInput
{
    public function __construct(#[Header] public string $trace)
    {
    }
}

final class RequiredPathHandler extends TypedHandler
{
    public function handleDto(RequiredPathInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class RequiredPathInput
{
    public function __construct(#[Path] public string $id)
    {
    }
}

final readonly class OpenApiMixedInput
{
    public function __construct(
        #[Body] public string $email,
        #[Query] public int $page = 1,
    ) {
    }
}

final class OpenApiMixedHandler extends TypedHandler
{
    public function handleDto(OpenApiMixedInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ReadsSibling implements ConstraintInterface
{
    public function __construct(private string $other)
    {
    }

    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        return is_string($context->value($this->other)) ? null : 'sibling missing';
    }
}

final readonly class SiblingInner
{
    public function __construct(
        #[Body] public string $left,
        #[Body]
        #[ReadsSibling('left')]
        public string $right,
    ) {
    }
}

final readonly class SiblingOuter
{
    public function __construct(#[Body] public SiblingInner $inner)
    {
    }
}

final class SiblingHandler extends TypedHandler
{
    public function handleDto(SiblingOuter $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['right' => $input->inner->right]);
    }
}

final class FakeConstraintSpec implements \ArrayAccess
{
    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $offset === 'class' ? Range::class : ['min' => 1];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }
}

final class LooseBodyHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            new BodyInputParam('note')->setOpenApiSchema(['type' => 'string']),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
