<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;

/**
 * Api definitions covering every feature of the generated OpenAPI spec
 */
class ApiFixtures
{
    public const BASE_URL = 'https://example.com/';

    /** host url passed by OpenApiHandler as base url */
    public const HOST_URL = 'https://example.com';

    public static function request(): Request
    {
        return new Request(new UrlScript(self::BASE_URL));
    }

    public static function apiLink(): ApiLink
    {
        $router = new RouteList();
        $router->addRoute('api/v<version>/<package>[/<apiAction>]', 'Api:Api:default');
        return new ApiLink(new LinkGenerator($router, new UrlScript(self::BASE_URL)));
    }

    public static function apiDecider(): ApiDecider
    {
        $tokenRepository = new StaticTokenRepository();
        $ipDetector = new StaticIpDetector('127.0.0.1');

        $apiDecider = new ApiDecider(new Container());
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'users'), new UserListHandler(), new BearerTokenAuthorization($tokenRepository, $ipDetector));
        $apiDecider->addApi(new EndpointIdentifier('POST', '1', 'users'), new UserCreateHandler(), new BasicAuthentication(['admin' => 'secret'], self::request()));
        $apiDecider->addApi(new EndpointIdentifier('PUT', '1', 'users', 'detail'), new UserUpdateHandler(), new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector));
        $apiDecider->addApi(new EndpointIdentifier('POST', '1', 'import', 'raw'), new RawBodyHandler(), new HeaderApiKeyAuthentication('X-Api-Key', $tokenRepository, $ipDetector));
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'import', 'status'), new PingHandler(), new CookieApiKeyAuthentication('api-token', $tokenRepository, $ipDetector));
        $apiDecider->addApi(new EndpointIdentifier('GET', '1', 'ping'), new PingHandler(), new NoAuthorization());
        $apiDecider->addApi(new EndpointIdentifier('GET', '2', 'ping'), new PingHandler(), new NoAuthorization());
        $apiDecider->addApi(new EndpointIdentifier('GET', '2', 'users'), new UserListHandler(), new NoAuthorization());
        return $apiDecider;
    }
}
