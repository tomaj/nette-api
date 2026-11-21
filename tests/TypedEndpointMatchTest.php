<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test;

use Tomaj\NetteApi\Handlers\AlwaysOkHandler;
use Tomaj\NetteApi\Handlers\DefaultHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Params\PathInputParam;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Nette\Application\Request;
use Nette\Http\IRequest;
use Nette\Http\Request as HttpRequest;
use Nette\Http\Response as HttpResponse;
use Nette\Http\UrlScript;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;

final class TypedEndpointMatchTest extends TestCase
{
    public function testTemplateMatchWinsWhenTheRouteSplitsTheId(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $exact = self::resolve($decider, 'GET', '1', 'users', 'list', null);
        $this->assertInstanceOf(AlwaysOkHandler::class, $exact->getHandler());

        $matched = self::resolve($decider, 'GET', '1', 'users', '42', 'detail');
        $this->assertInstanceOf(UserDetailHandler::class, $matched->getHandler());
        $this->assertSame('v1/users/{id}/detail', $matched->getHandler()->getEndpoint()?->getUrl());

        $missing = self::resolve($decider, 'GET', '1', 'users', '42', null);
        $this->assertInstanceOf(DefaultHandler::class, $missing->getHandler());
    }

    public function testAPlainHandlerWithAPathParamMatchesATemplate(): void
    {
        $handler = new class extends AlwaysOkHandler {
            public function params(): array
            {
                return [new PathInputParam('id')];
            }
        };
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), $handler, new NoAuthorization());

        $matched = self::resolve($decider, 'GET', '1', 'users', '42', 'detail');
        $this->assertSame($handler, $matched->getHandler());
    }

    public function testAShorterStaticEndpointDoesNotAnswerAPathThatATemplateMatches(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '7'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $presenter = new ApiPresenter();
        $presenter->apiDecider = $decider;
        $presenter->response = new HttpResponse();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);
        $presenter->context = new class extends Container {
            public string $path = '';

            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === IRequest::class ? new HttpRequest(new UrlScript('https://example.com' . $this->path)) : null;
            }
        };

        $presenter->context->path = '/api/v1/users/7/detail';
        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '7', 'params' => 'detail']));
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(7, $response->getPayload()['id']);

        $presenter->context->path = '/api/v1/users/7/other';
        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '7', 'params' => 'other']));
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(['status' => 'ok'], $response->getPayload(), 'without a matching template the static endpoint still answers, as in 3.x');
    }

    public function testALiteralPlaceholderInTheRequestIsAValueOfTheTemplate(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}'), self::echoPathHandler(), new NoAuthorization());
        $presenter = self::presenter($decider);

        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '{id}']));
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(['id' => '{id}'], $response->getPayload());

        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '{id}', 'params' => 'more']));
        $this->assertSame(404, $response->getCode(), 'the literal URL of a template is not an endpoint of a request');
    }

    public function testATemplateIsMatchedFromTheRouterValuesWithoutAnHttpRequest(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 2, 'users', '{id}/detail'), self::echoPathHandler(), new NoAuthorization());

        // for example a forwarded request, or a route mask without the "v" prefix
        $response = self::presenter($decider)->run(new Request('Api:Api:default', 'GET', ['version' => '2', 'package' => 'users', 'apiAction' => 'a b', 'params' => 'detail']));
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(['id' => 'a b'], $response->getPayload());
    }

    public function testAnEncodedSlashDoesNotSplitAValueIntoTwoSegments(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/{part}/detail'), self::echoPathHandler(['id', 'part']), new NoAuthorization());
        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => 'a', 'params' => 'b/detail']);

        $this->assertSame(404, self::presenter($decider, '/api/v1/users/a%2fb/detail')->run($request)->getCode());
        $this->assertSame(['id' => 'a', 'part' => 'b'], self::payload(self::presenter($decider, '/api/v1/users/a/b/detail')->run($request)));
    }

    public function testAQueryParameterNamedParamsDoesNotChooseTheEndpoint(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '7'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), self::echoPathHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'notes', '{id}/{part}'), self::echoPathHandler(['id', 'part']), new NoAuthorization());
        // a route mask without <params> takes "params" from the query string
        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '7', 'params' => 'detail']);

        $this->assertSame(['status' => 'ok'], self::payload(self::presenter($decider, '/api/v1/users/7?params=detail')->run($request)));
        $this->assertSame(['id' => '7'], self::payload(self::presenter($decider, '/api/v1/users/7/detail?params=detail')->run($request)), 'the same value in the path');
        $this->assertSame(['id' => '7'], self::payload(self::presenter($decider, '/api/v1/users/7/detail?params=other')->run($request)), 'the path wins over the query');

        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'notes', 'apiAction' => '7', 'params' => 'a b']);
        $this->assertSame(['id' => '7', 'part' => 'a b'], self::payload(self::presenter($decider, '/api/v1/notes/7/a%20b?params=a%20b')->run($request)), 'the path is compared decoded');

        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'xdetail'), new AlwaysOkHandler(), new NoAuthorization());
        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => 'xdetail', 'params' => 'detail']);
        $this->assertSame(['status' => 'ok'], self::payload(self::presenter($decider, '/api/v1/users/xdetail?params=detail')->run($request)), 'the path ends with a whole segment');
    }

    public function testAStaticEndpointWinsWhenTheQueryParamsAreIgnored(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '7'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}'), self::echoPathHandler(), new NoAuthorization());
        $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '7', 'params' => 'detail']);

        $this->assertSame(['status' => 'ok'], self::payload(self::presenter($decider, '/api/v1/users/7?params=detail')->run($request)));
    }

    public function testExtraSegmentsWithoutAnHttpRequestServiceAreMatched(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), self::echoPathHandler(), new NoAuthorization());
        $presenter = self::presenter($decider);
        $presenter->context = new Container();

        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => '7', 'params' => 'detail']));
        $this->assertSame(['id' => '7'], self::payload($response));
    }

    public function testADotSegmentIsNotAPathValue(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), self::echoPathHandler(), new NoAuthorization());

        foreach (['.', '..'] as $value) {
            $request = new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'users', 'apiAction' => $value, 'params' => 'detail']);
            $this->assertSame(404, self::presenter($decider)->run($request)->getCode(), $value);
        }
    }

    public function testGetApiKeepsItsFourArgumentContract(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $this->assertInstanceOf(AlwaysOkHandler::class, $decider->getApi('GET', '1', 'users', 'list')->getHandler());
        $this->assertInstanceOf(DefaultHandler::class, $decider->getApi('GET', '1', 'users', '42')->getHandler());
        $this->assertNull($decider->matchPath('GET', '1', 'users', '42'));
        $this->assertNull($decider->matchPath('GET', '1', 'users', '42', ''));
        $this->assertInstanceOf(UserDetailHandler::class, $decider->matchPath('GET', '1', 'users', '42', 'detail')?->getHandler());
    }

    public function testASubclassCanKeepOverridingTheFourArgumentGetApi(): void
    {
        $decider = new class (new Container()) extends ApiDecider {
            public function getApi(string $method, string $version, string $package, ?string $apiAction = null): Api
            {
                return new Api(new EndpointIdentifier($method, (int) $version, $package, $apiAction), new AlwaysOkHandler(), new NoAuthorization());
            }
        };

        $this->assertInstanceOf(AlwaysOkHandler::class, $decider->getApi('GET', '1', 'users', 'x')->getHandler());
    }

    public function testOverlappingTemplatesAreReported(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}'), new UserDetailHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('POST', 1, 'users', '{slug}'), new UserDetailHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 2, 'users', '{slug}'), new UserDetailHandler(), new NoAuthorization());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("GET endpoints 'v1/users/{id}' and 'v1/users/{slug}' can match the same request.");
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{slug}'), new UserDetailHandler(), new NoAuthorization());
    }

    public function testOptionsOnATemplateUsesThePreflightHandler(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->enableGlobalPreflight();
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $api = self::resolve($decider, 'OPTIONS', '1', 'users', '42', 'detail');
        $this->assertInstanceOf(CorsPreflightHandler::class, $api->getHandler());
    }

    public function testMatchPathNormalisesTheMethodAndAnEmptyAction(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->enableGlobalPreflight();
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $preflight = $decider->matchPath('options', '1', 'users', '', '42/detail');
        $this->assertInstanceOf(CorsPreflightHandler::class, $preflight?->getHandler());
        $this->assertSame('OPTIONS', $preflight->getEndpoint()->getMethod());
        $this->assertNull($preflight->getEndpoint()->getApiAction());

        $named = $decider->matchPath('OPTIONS', '1', 'users', '42', 'detail');
        $this->assertSame('42', $named?->getEndpoint()->getApiAction());

        $matched = $decider->matchPath('get', '1', 'users', '42', 'detail');
        $this->assertInstanceOf(UserDetailHandler::class, $matched?->getHandler());
    }

    public function testLinkKeepsPathPlaceholders(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), ['limit' => 10]);
        $this->assertStringContainsString('/api/v1/users/{id}/detail', $link);
        $this->assertStringContainsString('limit=10', $link);
    }

    public function testStaticLinkIsUnchanged(): void
    {
        $link = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', 'list'));
        $this->assertSame('https://example.com/api/v1/users/list', $link);
        $withQuery = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', 'list'), ['q' => '1']);
        $this->assertSame('https://example.com/api/v1/users/list?q=1', $withQuery);
        $placeholder = ApiFixtures::apiLink()->link(new EndpointIdentifier('GET', 1, 'users', '{id}'));
        $this->assertStringContainsString('/api/v1/users/{id}', $placeholder);
    }

    private static function resolve(ApiDecider $decider, string $method, string $version, string $package, string $apiAction, ?string $params): Api
    {
        $api = $decider->getApi($method, $version, $package, $apiAction);

        return $api->getHandler() instanceof DefaultHandler ? ($decider->matchPath($method, $version, $package, $apiAction, $params) ?? $api) : $api;
    }

    public function testPathCaptureDoesNotLeakIntoTheNextEndpoint(): void
    {
        $decider = new ApiDecider(new Container());
        $decider->enableGlobalPreflight();
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'list'), new AlwaysOkHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());
        $decider->addApi(new EndpointIdentifier('GET', 2, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());

        $matched = self::resolve($decider, 'GET', '1', 'users', '42', 'detail');
        $id = null;
        foreach ($matched->getHandler()->params() as $param) {
            if ($param->getKey() === 'id') {
                $id = $param->getValue();
            }
        }
        $this->assertSame(42, $id);

        $static = self::resolve($decider, 'GET', '1', 'users', 'list', null);
        $this->assertInstanceOf(AlwaysOkHandler::class, $static->getHandler());
        foreach (new UserDetailHandler()->params() as $param) {
            if ($param->getKey() === 'id') {
                $this->assertNull($param->getValue());
            }
        }

        $post = self::resolve($decider, 'POST', '1', 'users', '42', 'detail');
        $this->assertInstanceOf(DefaultHandler::class, $post->getHandler());
        $withPreflight = self::resolve($decider, 'GET', '1', 'users', '7', 'detail');
        $this->assertInstanceOf(UserDetailHandler::class, $withPreflight->getHandler());
        $otherVersion = self::resolve($decider, 'GET', '3', 'users', '7', 'detail');
        $this->assertInstanceOf(DefaultHandler::class, $otherVersion->getHandler());

        $decider->addApi(new EndpointIdentifier('GET', 1, 'users', 'detail'), new UserDetailHandler(), new NoAuthorization());
        $this->expectException(\LogicException::class);
        $decider->getApi('GET', '1', 'users', 'detail');
    }

    /**
     * @param list<string> $names
     */
    private static function echoPathHandler(array $names = ['id']): AlwaysOkHandler
    {
        return new class ($names) extends AlwaysOkHandler {
            /** @param list<string> $names */
            public function __construct(private readonly array $names)
            {
                parent::__construct();
            }

            public function params(): array
            {
                return array_map(static fn (string $name): PathInputParam => new PathInputParam($name), $this->names);
            }

            public function handle(array $params): ResponseInterface
            {
                return new JsonApiResponse(200, $params);
            }
        };
    }

    private static function presenter(ApiDecider $decider, ?string $path = null): ApiPresenter
    {
        $presenter = new ApiPresenter();
        $presenter->apiDecider = $decider;
        $presenter->response = new HttpResponse();
        $presenter->outputConfigurator = new DebuggerConfigurator();
        $presenter->errorHandler = new DefaultErrorHandler($presenter->outputConfigurator);
        $presenter->context = new class ($path) extends Container {
            public function __construct(private readonly ?string $path)
            {
                parent::__construct();
            }

            public function getByType(string $type, bool $throw = true): ?object
            {
                return $type === IRequest::class && $this->path !== null ? new HttpRequest(new UrlScript('https://example.com' . $this->path)) : null;
            }
        };

        return $presenter;
    }

    /**
     * @return mixed
     */
    private static function payload(\Nette\Application\Response $response): mixed
    {
        return $response instanceof JsonApiResponse ? $response->getPayload() : null;
    }
}
