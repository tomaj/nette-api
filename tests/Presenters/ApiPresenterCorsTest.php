<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use PHPUnit\Framework\Attributes\RequiresFunction;
use InvalidArgumentException;
use Nette\Application\Request;
use Nette\DI\Container;
use Nette\Http\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;

/**
 * Nette sends headers with header(); in CLI they are only observable through xdebug_get_headers().
 */
#[RequiresFunction('xdebug_get_headers')]
final class ApiPresenterCorsTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    private function presenter(): ApiPresenter
    {
        $apiDecider = new ApiDecider(new Container());
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'test', 'api'), new AlwaysOkHandler(), new NoAuthorization());

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $apiDecider;
        $presenter->response = new HttpResponse();
        $presenter->context = new Container();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);
        return $presenter;
    }

    private function runApi(ApiPresenter $presenter): array
    {
        $before = count(xdebug_get_headers());
        $result = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'test', 'apiAction' => 'api']));
        $this->assertSame(200, $result->getCode());
        return array_slice(xdebug_get_headers(), $before);
    }

    public function testDefaultSendsWildcard(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $this->assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Access-Control-Allow-Origin: *',
        ], $this->runApi($this->presenter()));
    }

    public function testAllowedOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $presenter = $this->presenter();
        $presenter->setCorsAllowedOrigins(['https://app.example.com']);

        $this->assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
            'Access-Control-Allow-Origin: https://app.example.com',
        ], $this->runApi($presenter));
    }

    public function testAllowedOriginWithCredentialsTakesPrecedenceOverCorsHeader(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $_SERVER['HTTP_REFERER'] = 'https://evil.example.com/page';
        $presenter = $this->presenter();
        $presenter->setCorsHeader('auto');
        $presenter->setCorsAllowedOrigins(['https://app.example.com'], true);

        $this->assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
            'Access-Control-Allow-Origin: https://app.example.com',
            'Access-Control-Allow-Credentials: true',
        ], $this->runApi($presenter));
    }

    public function testNotAllowedOrigin(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.com';
        $presenter = $this->presenter();
        $presenter->setCorsAllowedOrigins(['https://app.example.com'], true);

        $this->assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
        ], $this->runApi($presenter));
    }

    public function testMissingOrigin(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);
        $presenter = $this->presenter();
        $presenter->setCorsAllowedOrigins(['https://app.example.com'], true);

        $this->assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
        ], $this->runApi($presenter));
    }

    public function testInvalidAllowlistFailsEarly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->presenter()->setCorsAllowedOrigins(['*']);
    }
}
