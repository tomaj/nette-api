<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

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
 *
 * @requires function xdebug_get_headers
 */
class ApiPresenterCorsTest extends TestCase
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
        self::assertSame(200, $result->getCode());
        return array_slice(xdebug_get_headers(), $before);
    }

    public function testDefaultSendsWildcard()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        self::assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Access-Control-Allow-Origin: *',
        ], $this->runApi($this->presenter()));
    }

    public function testAllowedOrigin()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $presenter = $this->presenter();
        $presenter->setCorsAllowedOrigins(['https://app.example.com']);

        self::assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
            'Access-Control-Allow-Origin: https://app.example.com',
        ], $this->runApi($presenter));
    }

    public function testAllowedOriginWithCredentialsTakesPrecedenceOverCorsHeader()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $_SERVER['HTTP_REFERER'] = 'https://evil.example.com/page';
        $presenter = $this->presenter();
        $presenter->setCorsHeader('auto');
        $presenter->setCorsAllowedOrigins(['https://app.example.com'], true);

        self::assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
            'Access-Control-Allow-Origin: https://app.example.com',
            'Access-Control-Allow-Credentials: true',
        ], $this->runApi($presenter));
    }

    public function testNotAllowedOrigin()
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.com';
        $presenter = $this->presenter();
        $presenter->setCorsAllowedOrigins(['https://app.example.com'], true);

        self::assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
        ], $this->runApi($presenter));
    }

    public function testMissingOrigin()
    {
        unset($_SERVER['HTTP_ORIGIN']);
        $presenter = $this->presenter();
        $presenter->setCorsAllowedOrigins(['https://app.example.com'], true);

        self::assertSame([
            'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS',
            'Vary: Origin',
        ], $this->runApi($presenter));
    }

    public function testInvalidAllowlistFailsEarly()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->presenter()->setCorsAllowedOrigins(['*']);
    }
}
