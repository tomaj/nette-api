<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\OpenApi\ResponseBuilder;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;

final class ProblemDetailsErrorHandlerTest extends TestCase
{
    use LogDirectory;

    private function handler(bool $debug = false, ?string $base = null): ProblemDetailsErrorHandler
    {
        return new ProblemDetailsErrorHandler(new StaticConfigurator($debug), $base);
    }

    public function testHandleProduction(): void
    {
        $response = $this->handler()->handle(new RuntimeException('secret'), []);
        $this->assertSame(500, $response->getCode());
        $this->assertSame('application/problem+json', $response->getContentType());
        $this->assertSame(['type' => 'about:blank', 'status' => 500, 'title' => 'Internal Server Error'], $response->getProblem()->toArray());
        $this->assertStringContainsString('secret', $this->logged('exception'));
    }

    public function testHandleDebug(): void
    {
        $response = $this->handler(true, 'https://api.example.com/problems/')->handle(new RuntimeException('secret'), []);
        $this->assertSame(['type' => 'https://api.example.com/problems/internal-error', 'status' => 500, 'title' => 'Internal Server Error', 'detail' => 'secret'], $response->getProblem()->toArray());
    }

    public function testInputParams(): void
    {
        $response = $this->handler(false, 'https://e.com/p/')->handleInputParams([
            'email' => ['Field is required', 'Value is not valid email'],
            'age' => 'Not a number',
            'size' => [5],
            7 => [['nested' => true]],
        ]);
        $this->assertSame(400, $response->getCode());
        $this->assertSame([
            'type' => 'https://e.com/p/validation-error',
            'status' => 400,
            'title' => 'Bad Request',
            'detail' => 'Request parameters are not valid.',
            'errors' => [
                ['parameter' => 'email', 'detail' => 'Field is required'],
                ['parameter' => 'email', 'detail' => 'Value is not valid email'],
                ['parameter' => 'age', 'detail' => 'Not a number'],
                ['parameter' => 'size', 'detail' => '5'],
                ['parameter' => '7', 'detail' => '{"nested":true}'],
            ],
        ], $response->getProblem()->toArray());
    }

    public function testSchema(): void
    {
        $errors = [['wrong output']];
        $response = $this->handler()->handleSchema($errors, []);
        $this->assertSame(['type' => 'about:blank', 'status' => 500, 'title' => 'Internal Server Error'], $response->getProblem()->toArray());
        $this->assertStringContainsString('wrong output', $this->logged('error'));
        $this->assertSame(
            ['type' => 'x/invalid-output', 'status' => 500, 'title' => 'Internal Server Error', 'outputErrors' => $errors],
            $this->handler(true, 'x/')->handleSchema($errors, [])->getProblem()->toArray(),
        );
    }

    public function testTypeBaseUriGetsExactlyOneTrailingSlash(): void
    {
        foreach (['https://e.com/problems', 'https://e.com/problems/', 'https://e.com/problems//'] as $base) {
            $this->assertSame('https://e.com/problems/not-found', $this->handler(false, $base)->handleNotFound(new EndpointIdentifier('GET', '1', 'x'))->getProblem()->type, $base);
        }
    }

    public function testEmptyTypeBaseUriSendsAboutBlank(): void
    {
        foreach (['', '/'] as $base) {
            $this->assertSame('about:blank', $this->handler(false, $base)->handleNotFound(new EndpointIdentifier('GET', '1', 'x'))->getProblem()->type, $base);
        }
    }

    public function testAuthorization(): void
    {
        $auth = new BearerTokenAuthorization(new StaticTokenRepository(), new StaticIpDetector('127.0.0.1'));
        $auth->authorized();
        $response = $this->handler(false, 'x/')->handleAuthorization($auth, []);
        $this->assertSame(401, $response->getCode());
        $this->assertSame(['type' => 'x/unauthorized', 'status' => 401, 'title' => 'Unauthorized', 'detail' => 'Authorization header HTTP_Authorization is not set'], $response->getProblem()->toArray());
    }

    public function testAuthorizationException(): void
    {
        $response = $this->handler()->handleAuthorizationException(new RuntimeException('token db down'), []);
        $this->assertSame(['type' => 'about:blank', 'status' => 500, 'title' => 'Internal Server Error'], $response->getProblem()->toArray());
        $this->assertStringContainsString('token db down', $this->logged('exception'));
        $this->assertSame('token db down', $this->handler(true)->handleAuthorizationException(new RuntimeException('token db down'), [])->getProblem()->detail);
    }

    public function testNotFound(): void
    {
        $response = $this->handler(false, 'x/')->handleNotFound(new EndpointIdentifier('GET', '1', 'users'));
        $this->assertSame(404, $response->getCode());
        $this->assertSame(['type' => 'x/not-found', 'status' => 404, 'title' => 'Not Found', 'detail' => 'Unknown api endpoint'], $response->getProblem()->toArray());
    }

    public function testRateLimit(): void
    {
        $response = $this->handler(false, 'x/')->handleRateLimit(new RateLimitResponse(100, 0, 42));
        $this->assertSame(429, $response->getCode());
        $this->assertSame(['type' => 'x/rate-limited', 'status' => 429, 'title' => 'Too Many Requests', 'detail' => 'Too many requests. Retry after 42 seconds.', 'limit' => 100, 'retryAfter' => 42], $response->getProblem()->toArray());

        $problem = $this->handler()->handleRateLimit(new RateLimitResponse(5, 0))->getProblem();
        $this->assertSame(['limit' => 5], $problem->extensions);
        $this->assertSame('Too many requests.', $problem->detail);
    }

    public function testEveryResponseMatchesTheOpenApiSchema(): void
    {
        $handler = $this->handler(true, 'https://e.com/p/');
        $auth = new BearerTokenAuthorization(new StaticTokenRepository([]), new StaticIpDetector('127.0.0.1'));
        $auth->authorized();
        $responses = [
            $handler->handle(new RuntimeException('secret'), []),
            $handler->handleInputParams(['email' => ['Field is required']]),
            $handler->handleSchema([['[Property id] The property id is required'], ['Response code doesn\'t match']], []),
            $handler->handleAuthorization($auth, []),
            $handler->handleAuthorizationException(new RuntimeException('secret'), []),
            $handler->handleNotFound(new EndpointIdentifier('GET', 1, 'missing')),
            $handler->handleRateLimit(new RateLimitResponse(100, 0, 42)),
        ];
        $schema = json_encode(ResponseBuilder::problemDetailsSchema(), JSON_THROW_ON_ERROR);
        foreach ($responses as $response) {
            $this->assertInstanceOf(ProblemDetailsResponse::class, $response);
            $body = json_decode(json_encode($response->getPayload(), JSON_THROW_ON_ERROR));
            $result = new JsonSchemaValidator()->validate($body, $schema);
            $this->assertTrue($result->isOk(), implode(', ', array_map(strval(...), $result->getErrors())));
        }
    }
}
