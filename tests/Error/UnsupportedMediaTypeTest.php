<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Error\NegotiatingErrorHandler;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Test\Error\StaticConfigurator;

final class UnsupportedMediaTypeTest extends TestCase
{
    public function testDefaultHandlerHidesTheDetailUntilItIsEnabled(): void
    {
        $hidden = new DefaultErrorHandler(new StaticConfigurator(false));
        $response = $hidden->handleUnsupportedMediaType("Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json.");
        $this->assertSame(415, $response->getCode());
        $this->assertSame(['status' => 'error', 'message' => 'unsupported media type'], $response->getPayload());

        $shown = new DefaultErrorHandler(new DebuggerConfigurator());
        $payload = $shown->handleUnsupportedMediaType("Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json.")->getPayload();
        $this->assertSame('unsupported media type', $payload['message']);
        $this->assertStringContainsString('text/csv', (string) $payload['detail']);
    }

    public function testProblemDetailsUses415AndTheSameDetail(): void
    {
        $handler = new ProblemDetailsErrorHandler(new DebuggerConfigurator(), 'https://api.example.com/problems');
        $response = $handler->handleUnsupportedMediaType('Content-Type is missing. Use one of: application/json.');
        $this->assertSame(415, $response->getCode());
        $this->assertSame('application/problem+json', $response->getContentType());
        $payload = $response->getPayload()->toArray();
        $this->assertSame(415, $payload['status']);
        $this->assertSame('Unsupported Media Type', $payload['title']);
        $this->assertSame('https://api.example.com/problems/unsupported-media-type', $payload['type']);
        $this->assertSame('Content-Type is missing. Use one of: application/json.', $payload['detail']);
    }

    public function testNegotiationSelectsProblemDetailsForTheAcceptHeader(): void
    {
        $http = new Response();
        $handler = new NegotiatingErrorHandler(
            new DefaultErrorHandler(new StaticConfigurator(true)),
            new ProblemDetailsErrorHandler(new StaticConfigurator(true)),
            new Request(new UrlScript('http://localhost/'), headers: ['Accept' => 'application/problem+json']),
            $http,
        );
        $response = $handler->handleUnsupportedMediaType('Content-Type is missing. Use one of: application/json.');
        $this->assertSame('application/problem+json', $response->getContentType());
    }
}
