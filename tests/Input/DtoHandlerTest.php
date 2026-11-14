<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\UnsupportedMediaTypeException;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Test\Input\Fixtures\AvatarHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\FilesHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\IssueHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ReportHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ScheduleHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;

final class DtoHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_X_REQUEST_ID']);
        RequestInput::reset();
        PathValues::reset();
    }

    public function testQueryDefaultsEnumAndStrictInteger(): void
    {
        $_GET = ['status' => 'active', 'priority' => '2', 'remember' => '1'];
        $payload = $this->payload(new ListUsersHandler());
        $this->assertSame(['page' => 1, 'limit' => 20, 'status' => 'active', 'priority' => 2, 'remember' => true], $payload);

        $_GET = ['page' => '1a'];
        RequestInput::reset();
        $errors = $this->errors(new ListUsersHandler());
        $this->assertSame(['page' => ['Value must be an integer']], $errors);
    }

    public function testQueryRejectsLooseBooleanAndEmptyString(): void
    {
        $_GET = ['remember' => 'yes'];
        $this->assertSame(['remember' => ['Value must be a boolean']], $this->errors(new ListUsersHandler()));

        $_GET = ['page' => ''];
        RequestInput::reset();
        $this->assertSame(['page' => ['Value must be an integer']], $this->errors(new ListUsersHandler()));
    }

    public function testPathHeaderAndQueryTogether(): void
    {
        $_GET = ['limit' => '10', 'status' => 'disabled'];
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-01';
        PathValues::set(['id' => '42']);
        $payload = $this->payload(new UserDetailHandler());
        $this->assertSame(['id' => 42, 'limit' => 10, 'status' => 'disabled', 'requestId' => 'req-01'], $payload);
    }

    public function testHeaderLookupIsCaseInsensitiveAndOptional(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = '';
        PathValues::set(['id' => '7']);
        $payload = $this->payload(new UserDetailHandler());
        $this->assertSame('', $payload['requestId']);

        unset($_SERVER['HTTP_X_REQUEST_ID']);
        RequestInput::reset();
        PathValues::set(['id' => '7']);
        $payload = $this->payload(new UserDetailHandler());
        $this->assertNull($payload['requestId']);
    }

    public function testNestedJsonCollectsEveryFieldPath(): void
    {
        $this->json([
            'customerEmail' => 'nope',
            'address' => ['street' => '', 'postalCode' => 'abc'],
            'items' => [
                ['sku' => 'BOOK-1', 'quantity' => 2],
                ['sku' => 'PEN-2', 'quantity' => 0],
            ],
        ]);
        $this->assertSame([
            'customerEmail' => ['Email must be a valid email address'],
            'address.street' => ['Value must have between 1 and 120 characters'],
            'address.postalCode' => ['Value must match the pattern /^[0-9]{5}$/'],
            'items[1].quantity' => ['Value must be at least 1'],
        ], $this->errors(new CreateOrderHandler()));
    }

    public function testJsonSuccessIgnoresUnknownFieldsAndParsesRfc3339(): void
    {
        $this->json([
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => 'Main 1', 'postalCode' => '81101', 'extra' => true],
            'items' => [['sku' => 'BOOK-1', 'quantity' => 2]],
            'deliveryAt' => '2026-11-06T14:30:00+01:00',
            'coupon' => 'FREE',
        ]);
        $payload = $this->payload(new CreateOrderHandler());
        $this->assertSame('ada@example.com', $payload['email']);
        $this->assertSame('Main 1', $payload['street']);
        $this->assertSame(2, $payload['quantity']);
        $this->assertSame('2026-11-06T14:30:00+01:00', $payload['deliveryAt']);
    }

    public function testExplicitNullAndMissingNestedObject(): void
    {
        $this->json([
            'customerEmail' => 'ada@example.com',
            'address' => null,
            'items' => [['sku' => 'BOOK-1', 'quantity' => 1]],
        ]);
        $this->assertSame(['address' => ['Value must not be null']], $this->errors(new CreateOrderHandler()));

        $this->json([
            'customerEmail' => 'ada@example.com',
            'items' => [['sku' => 'BOOK-1', 'quantity' => 1]],
        ]);
        $this->assertSame(['address' => ['Field is required']], $this->errors(new CreateOrderHandler()));
    }

    public function testMalformedJsonUsesTheParserMessage(): void
    {
        RequestInput::reset();
        RequestInput::setRawBody('{"customerEmail":');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $errors = $this->errors(new CreateOrderHandler());
        $this->assertArrayHasKey('body', $errors);
        $this->assertStringStartsWith('Syntax error', $errors['body'][0]);
    }

    public function testJsonRootMustBeAnObject(): void
    {
        RequestInput::reset();
        RequestInput::setRawBody('[1]');
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertSame(['body' => ['Value must be an object']], $this->errors(new CreateOrderHandler()));
    }

    public function testFormBracketNotationUsesTheSameRules(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => 'Main 1', 'postalCode' => '81101'],
            'items' => [['sku' => 'BOOK-1', 'quantity' => '2']],
        ];
        $payload = $this->payload(new CreateOrderHandler());
        $this->assertSame(2, $payload['quantity']);
        $this->assertNull($payload['deliveryAt']);
    }

    public function testFormDoesNotTreatTheWordNullAsNull(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => 'Main 1', 'postalCode' => '81101'],
            'items' => [['sku' => 'BOOK-1', 'quantity' => '2pcs']],
            'deliveryAt' => 'null',
        ];
        $errors = $this->errors(new CreateOrderHandler());
        $this->assertSame(['items[0].quantity' => ['Value must be an integer']], ['items[0].quantity' => $errors['items[0].quantity']]);
        $this->assertArrayHasKey('deliveryAt', $errors);
    }

    public function testPutFormBodyIsParsedFromTheRawBody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        RequestInput::setRawBody('customerEmail=ada%40example.com&address[street]=Main+1&address[postalCode]=81101&items[0][sku]=BOOK-1&items[0][quantity]=2');
        $payload = $this->payload(new CreateOrderHandler());
        $this->assertSame(2, $payload['quantity']);
    }

    public function testUnsupportedAndMissingContentType(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'text/csv; charset=utf-8';
        try {
            $this->errors(new CreateOrderHandler());
            $this->fail('csv was accepted');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame("Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded.", $exception->getMessage());
        }

        unset($_SERVER['CONTENT_TYPE']);
        RequestInput::reset();
        try {
            $this->errors(new CreateOrderHandler());
            $this->fail('missing type was accepted');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame('Content-Type is missing. Use one of: application/json, application/x-www-form-urlencoded.', $exception->getMessage());
        }
    }

    public function testCharsetOnJsonIsIgnored(): void
    {
        $this->json([
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => 'Main 1', 'postalCode' => '81101'],
            'items' => [['sku' => 'BOOK-1', 'quantity' => 1]],
        ]);
        $_SERVER['CONTENT_TYPE'] = 'Application/JSON; charset=utf-8';
        $this->assertFalse($this->processor(new CreateOrderHandler())->isError());
    }

    public function testTooLongListIsRejectedBeforeItsItemsAreMapped(): void
    {
        $this->json([
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => 'Main 1', 'postalCode' => '81101'],
            'items' => array_fill(0, 51, ['sku' => 'BOOK-1', 'quantity' => 'not a number']),
        ]);
        $this->assertSame(['items' => ['Value must have between 1 and 50 items']], $this->errors(new CreateOrderHandler()));
        $this->json([
            'customerEmail' => 'ada@example.com',
            'address' => ['street' => 'Main 1', 'postalCode' => '81101'],
            'items' => array_fill(0, 50, ['sku' => 'BOOK-1', 'quantity' => 1]),
        ]);
        $this->assertFalse($this->processor(new CreateOrderHandler())->isError());
    }

    public function testDateCasterThenConstraintMessage(): void
    {
        $this->json(['issuedOn' => '31.02.2026']);
        $this->assertSame(['issuedOn' => ['Date must match d.m.Y, for example 24.12.2026']], $this->errors(new IssueHandler()));

        $this->json(['issuedOn' => '24.12.2026']);
        $this->assertSame(['issuedOn' => '2026-12-24'], $this->payload(new IssueHandler()));
    }

    public function testSplitCasterOnAQueryList(): void
    {
        $_GET = ['columns' => 'id,createdAt,total'];
        $this->assertSame(['columns' => ['id', 'createdAt', 'total']], $this->payload(new ReportHandler()));

        $_GET = ['columns' => ''];
        RequestInput::reset();
        $this->assertSame(['columns' => ['']], $this->payload(new ReportHandler()));
    }

    public function testCrossFieldConstraint(): void
    {
        $this->json([
            'startsAt' => '2026-11-06T10:00:00Z',
            'endsAt' => '2026-11-06T09:00:00Z',
        ]);
        $this->assertSame(['endsAt' => ['Must be later than startsAt']], $this->errors(new ScheduleHandler()));

        $this->json([
            'startsAt' => '2026-11-06T10:00:00Z',
            'endsAt' => '2026-11-06T11:00:00Z',
        ]);
        $this->assertSame(['ok' => true], $this->payload(new ScheduleHandler()));
    }

    public function testSingleAndMultipleUploads(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 12]];
        $payload = $this->payload(new AvatarHandler());
        $this->assertSame('a.png', $payload['file']);
        $this->assertSame(12, $payload['size']);
        $this->assertSame(UPLOAD_ERR_OK, $payload['error']);

        $_POST = [];
        $_FILES = ['files' => [
            'name' => ['a.txt', 'b.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE],
            'size' => [1, 2],
        ]];
        RequestInput::reset();
        $payload = $this->payload(new FilesHandler());
        $this->assertSame(2, $payload['count']);
        $this->assertSame('a.txt', $payload['name']);
    }

    public function testMissingFileIsRequired(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
        $_POST = ['displayName' => 'Ada'];
        $this->assertSame(['avatar' => ['Field is required']], $this->errors(new AvatarHandler()));
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
        $_POST = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $handler): array
    {
        $processor = $this->processor($handler);
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));
        $response = $handler->handle($processor->getValues());

        return $response->getPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function errors(object $handler): array
    {
        $processor = $this->processor($handler);
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
    }

    private function processor(object $handler): ParamsProcessor
    {
        return new ParamsProcessor($handler->params());
    }
}
