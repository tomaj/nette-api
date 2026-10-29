<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use Tomaj\NetteApi\Response\JsonApiResponse;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Error\NegotiatingErrorHandler;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class NegotiatingErrorHandlerTest extends TestCase
{
    use LogDirectory;

    /** @var list<array{string, string}> headers added through IResponse::addHeader() */
    private array $addedHeaders = [];

    private function handler(?string $accept): NegotiatingErrorHandler
    {
        $headers = $accept === null ? [] : ['accept' => $accept];
        $configurator = new StaticConfigurator(false);
        $httpResponse = $this->createStub(IResponse::class);
        $httpResponse->method('addHeader')->willReturnCallback(function (string $name, string $value) use ($httpResponse): IResponse {
            $this->addedHeaders[] = [$name, $value];
            return $httpResponse;
        });
        return new NegotiatingErrorHandler(
            new DefaultErrorHandler($configurator),
            new ProblemDetailsErrorHandler($configurator),
            new Request(new UrlScript('http://localhost/'), headers: $headers),
            $httpResponse,
        );
    }

    /**
     * @return \Iterator<string, array{(string | null), string}>
     */
    public static function accepts(): \Iterator
    {
        yield 'none' => [null, 'application/json'];
        yield 'json' => ['application/json', 'application/json'];
        yield 'any' => ['*/*', 'application/json'];
        yield 'any application type' => ['application/*', 'application/json'];
        yield 'problem' => ['application/problem+json', 'application/problem+json'];
        yield 'upper case' => ['Application/Problem+JSON', 'application/problem+json'];
        yield 'list' => ['application/json, application/problem+json;q=0.5', 'application/problem+json'];
        yield 'spaces' => ['text/html ,  application/problem+json ; q=0.9', 'application/problem+json'];
        yield 'refused' => ['application/problem+json;q=0, application/json', 'application/json'];
        yield 'refused with space' => ['application/problem+json; q=0', 'application/json'];
        yield 'refused with decimals' => ['application/problem+json;q=0.000', 'application/json'];
        yield 'refused with spaces around' => ['application/problem+json;q = 0.0', 'application/json'];
        yield 'small but accepted' => ['application/problem+json;q=0.001', 'application/problem+json'];
        yield 'q in the middle' => ['application/problem+json;q=0.01', 'application/problem+json'];
        yield 'other param zero' => ['application/problem+json;level=0', 'application/problem+json'];
        yield 'q=0 as a media type' => ['q=0', 'application/json'];
        yield 'other param ending with q' => ['application/problem+json;xq=0', 'application/problem+json'];
        yield 'prefix only' => ['application/problem+jsonx', 'application/json'];
    }

    #[DataProvider('accepts')]
    public function testSelect(?string $accept, string $contentType): void
    {
        $handler = $this->handler($accept);
        $responses = [
            $handler->handle(new RuntimeException('x'), []),
            $handler->handleInputParams(['a' => ['b']]),
            $handler->handleSchema([], []),
            $handler->handleAuthorization(new NoAuthorization(), []),
            $handler->handleAuthorizationException(new RuntimeException('x'), []),
            $handler->handleNotFound(new EndpointIdentifier('GET', '1', 'x')),
            $handler->handleRateLimit(new RateLimitResponse(1, 0, 1)),
        ];
        $this->assertSame([500, 400, 500, 401, 500, 404, 429], array_map(fn (ResponseInterface $r): int => $r->getCode(), $responses));
        foreach ($responses as $response) {
            $this->assertInstanceOf(JsonApiResponse::class, $response);
            $this->assertSame($contentType, $response->getContentType());
        }

        // both formats depend on Accept, so every error response varies by it
        $this->assertSame(array_fill(0, 7, ['Vary', 'Accept']), $this->addedHeaders);
    }
}
