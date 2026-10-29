<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;

final class DefaultErrorHandlerTest extends TestCase
{
    use LogDirectory;

    public function testHandleHidesMessageInProduction(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(false))->handle(new RuntimeException('db password wrong'), []);
        $this->assertSame(500, $response->getCode());
        $this->assertSame('application/json', $response->getContentType());
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error'], $response->getPayload());
        $this->assertStringContainsString('db password wrong', $this->logged('exception'));
    }

    public function testHandleShowsMessageInDebug(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(true))->handle(new RuntimeException('boom'), []);
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error', 'detail' => 'boom'], $response->getPayload());
    }

    public function testInputParams(): void
    {
        $errors = ['id' => ['Field is required']];
        $this->assertSame(['status' => 'error', 'message' => 'wrong input'], new DefaultErrorHandler(new StaticConfigurator(false))->handleInputParams($errors)->getPayload());
        $response = new DefaultErrorHandler(new StaticConfigurator(true))->handleInputParams($errors);
        $this->assertSame(400, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'wrong input', 'detail' => $errors], $response->getPayload());
        $this->assertSame('', $this->logged('error'));
    }

    public function testSchema(): void
    {
        $errors = [['wrong output']];
        $response = new DefaultErrorHandler(new StaticConfigurator(false))->handleSchema($errors, []);
        $this->assertSame(500, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error'], $response->getPayload());
        $this->assertStringContainsString('wrong output', $this->logged('error'));
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error', 'detail' => $errors], new DefaultErrorHandler(new StaticConfigurator(true))->handleSchema($errors, [])->getPayload());
    }

    public function testAuthorization(): void
    {
        $auth = new BearerTokenAuthorization(new StaticTokenRepository(), new StaticIpDetector('127.0.0.1'));
        $auth->authorized();
        $response = new DefaultErrorHandler(new StaticConfigurator(false))->handleAuthorization($auth, []);
        $this->assertSame(401, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Authorization header HTTP_Authorization is not set'], $response->getPayload());
    }

    /**
     * #188: message of an exception from authorization must not leak in production
     */
    public function testAuthorizationExceptionHidesMessageInProduction(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(false))->handleAuthorizationException(new RuntimeException('SQLSTATE[HY000] token table'), []);
        $this->assertSame(500, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error'], $response->getPayload());
        $this->assertStringContainsString('SQLSTATE[HY000] token table', $this->logged('exception'));
    }

    public function testAuthorizationExceptionShowsMessageInDebug(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(true))->handleAuthorizationException(new RuntimeException('token table'), []);
        $this->assertSame(['status' => 'error', 'message' => 'Internal server error', 'detail' => 'token table'], $response->getPayload());
    }

    public function testNotFound(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(true))->handleNotFound(new EndpointIdentifier('GET', '1', 'x'));
        $this->assertSame(404, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Unknown api endpoint'], $response->getPayload());
    }

    public function testRateLimit(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(true))->handleRateLimit(new RateLimitResponse(10, 0, 30));
        $this->assertSame(429, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'Too many requests. Retry after 30 seconds.'], $response->getPayload());
    }

    public function testRateLimitWithoutRetryAfter(): void
    {
        $response = new DefaultErrorHandler(new StaticConfigurator(true))->handleRateLimit(new RateLimitResponse(10, 0));
        $this->assertSame(['status' => 'error', 'message' => 'Too many requests.'], $response->getPayload());
    }
}
