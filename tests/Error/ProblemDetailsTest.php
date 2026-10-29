<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use InvalidArgumentException;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Error\ProblemDetails;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;

final class ProblemDetailsTest extends TestCase
{
    public function testMinimal(): void
    {
        $problem = new ProblemDetails(404, 'Not Found');
        $this->assertSame(['type' => 'about:blank', 'status' => 404, 'title' => 'Not Found'], $problem->toArray());
        $this->assertSame($problem->toArray(), $problem->jsonSerialize());
    }

    public function testAllMembers(): void
    {
        $problem = new ProblemDetails(400, 'Bad Request', 'Wrong', 'https://example.com/problems/x', '/orders/1', ['errors' => [['parameter' => 'id', 'detail' => 'required']]]);
        $this->assertSame([
            'type' => 'https://example.com/problems/x',
            'status' => 400,
            'title' => 'Bad Request',
            'detail' => 'Wrong',
            'instance' => '/orders/1',
            'errors' => [['parameter' => 'id', 'detail' => 'required']],
        ], $problem->toArray());
    }

    /**
     * @return \Iterator<string, array{int}>
     */
    public static function invalidStatuses(): \Iterator
    {
        yield '399' => [399];
        yield '600' => [600];
        yield '200' => [200];
    }

    #[DataProvider('invalidStatuses')]
    public function testInvalidStatus(int $status): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Problem details status must be 4xx or 5xx, ' . $status . ' given');
        new ProblemDetails($status, 'x');
    }

    public function testBoundaryStatuses(): void
    {
        $this->assertSame(400, new ProblemDetails(400, 'x')->status);
        $this->assertSame(599, new ProblemDetails(599, 'x')->status);
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function reserved(): \Iterator
    {
        yield 'type' => ['type'];
        yield 'status' => ['status'];
        yield 'title' => ['title'];
        yield 'detail' => ['detail'];
        yield 'instance' => ['instance'];
    }

    #[DataProvider('reserved')]
    public function testReservedExtension(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension member "' . $name . '" is reserved by RFC 9457');
        new ProblemDetails(400, 'x', null, 'about:blank', null, ['ok' => 1, $name => 'x']);
    }

    public function testResponse(): void
    {
        $problem = new ProblemDetails(409, 'Conflict', 'Order already paid');
        $response = new ProblemDetailsResponse($problem);
        $this->assertSame(409, $response->getCode());
        $this->assertSame($problem, $response->getProblem());
        $this->assertSame($problem, $response->getPayload());
        $this->assertSame('application/problem+json', $response->getContentType());

        $httpResponse = new Response();
        ob_start();
        $response->send(new Request(new UrlScript('http://localhost/')), $httpResponse);
        $body = (string) ob_get_clean();
        $this->assertSame('{"type":"about:blank","status":409,"title":"Conflict","detail":"Order already paid"}', $body);
    }
}
