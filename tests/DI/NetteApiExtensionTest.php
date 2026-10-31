<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Nette\Application\PresenterFactory;
use Nette\Application\Request;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Nette\DI\Container;
use Nette\DI\InvalidConfigurationException;
use Nette\DI\ServiceCreationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\DI\EndpointDefinition;
use Tomaj\NetteApi\DI\NetteApiExtension;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Error\NegotiatingErrorHandler;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Logger\LogMaskMode;
use Tomaj\NetteApi\Logger\LogValueMasker;
use Tomaj\NetteApi\Logger\LogValueMaskerInterface;
use Tomaj\NetteApi\Test\DI\Fixtures\UpperMasker;
use Tomaj\NetteApi\Misc\CorsOriginCallback;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Output\Configurator\EnvConfigurator;
use Tomaj\NetteApi\Output\Configurator\QueryConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\RateLimit\NoRateLimit;
use Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit;
use Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler;
use Tomaj\NetteApi\Test\DI\Fixtures\MemoryLogger;
use Tomaj\NetteApi\Test\Error\StaticConfigurator;

final class NetteApiExtensionTest extends TestCase
{
    private const string ROUTER = "services:\n    - Nette\\Application\\Routers\\RouteList\n";

    private const string FIXTURES = 'Tomaj\NetteApi\Test\DI\Fixtures';

    public function testEmptySectionRegistersTheApiServices(): void
    {
        $container = ContainerFactory::create('api:');

        $this->assertInstanceOf(ApiDecider::class, $container->getByType(ApiDecider::class));
        $this->assertSame([], $container->getByType(ApiDecider::class)->getApis());
        $this->assertInstanceOf(EnvConfigurator::class, $container->getByType(ConfiguratorInterface::class));
        $this->assertInstanceOf(DefaultErrorHandler::class, $container->getByType(ErrorHandlerInterface::class));
        $this->assertInstanceOf(IpDetector::class, $container->getByType(IpDetectorInterface::class));
        $this->assertNull($container->getByType(ApiLoggerInterface::class, false));

        $presenter = $container->getService('api.presenter');
        $this->assertInstanceOf(ApiPresenter::class, $presenter);
        $this->assertSame($container->getByType(ApiDecider::class), $presenter->apiDecider);
        $this->assertSame($container->getByType(ErrorHandlerInterface::class), $presenter->errorHandler);
        $this->assertSame($container->getByType(ConfiguratorInterface::class), $presenter->outputConfigurator);
        $this->assertSame('off', $this->property($presenter, 'corsHeader'), 'no CORS headers unless cors.mode says so');
        $this->assertNull($this->property($presenter, 'corsAllowlist'));
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADERS, $presenter->getMaskedLogHeaders(), 'logs are masked by default');
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADER_PATTERNS, $presenter->getMaskedLogHeaderPatterns());
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_QUERY_PARAMS, $presenter->getMaskedLogQueryParams());
        $this->assertSame(LogMaskMode::Full, $presenter->getLogMasking()->getMode());
        $this->assertNull($this->property($container->getByType(ApiDecider::class), 'globalPreflightHandler'));
        $this->assertNull($container->getByType(ApiPresenter::class, false), 'the presenter is not autowired');
    }

    public function testEndpointsAreRegisteredInOrderWithLazyHandlers(): void
    {
        GreetingHandler::$created = 0;
        $container = ContainerFactory::create(<<<NEON
            api:
                endpoints:
                    - {method: ' get ', version: 1, package: users, handler: Tomaj\NetteApi\Handlers\EchoHandler}
                    - {method: POST, version: '1.1', package: users, action: create, handler: \Tomaj\NetteApi\Handlers\EchoHandler}
                    - {method: GET, version: 2, package: hello, action: '', handler: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('Ahoj')}
            NEON);

        $decider = $container->getByType(ApiDecider::class);
        $this->assertSame(0, GreetingHandler::$created, 'handlers are created only when an endpoint is used');

        $apis = $decider->getApis();
        $this->assertSame(['GET v1/users/', 'POST v1.1/users/create', 'GET v2/hello/'], array_map(
            static fn (Api $api): string => $api->getEndpoint()->getMethod() . ' ' . $api->getEndpoint()->getUrl(),
            $apis,
        ));
        $this->assertNull($apis[2]->getEndpoint()->getApiAction());
        $this->assertSame($apis[0]->getHandler(), $apis[1]->getHandler(), 'one class, one service');
        $this->assertInstanceOf(EchoHandler::class, $apis[0]->getHandler());
        $this->assertSame($apis[0]->getHandler(), $container->getByType(EchoHandler::class));
        $handler = $apis[2]->getHandler();
        $this->assertInstanceOf(GreetingHandler::class, $handler);
        $this->assertSame('Ahoj', $handler->greeting);
        $this->assertNull($container->getByType(GreetingHandler::class, false), 'a statement handler is not autowired');
        foreach ($apis as $api) {
            $this->assertInstanceOf(NoAuthorization::class, $api->getAuthorization());
            $this->assertInstanceOf(NoRateLimit::class, $api->getRateLimit());
        }
    }

    public function testHandlerFromServicesSectionIsReused(): void
    {
        GreetingHandler::$created = 0;
        $container = ContainerFactory::create(<<<NEON
            services:
                greeting: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('Servus')
                other: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('Other')
                - Tomaj\NetteApi\Handlers\EchoHandler
            api:
                endpoints:
                    - {method: GET, version: 1, package: by-name, handler: @greeting}
                    - {method: GET, version: 1, package: by-class, handler: Tomaj\NetteApi\Handlers\EchoHandler}
            NEON);

        $decider = $container->getByType(ApiDecider::class);
        $this->assertSame(0, GreetingHandler::$created, 'a @service handler is created lazily too');
        $apis = $decider->getApis();
        $this->assertSame($container->getService('greeting'), $apis[0]->getHandler());
        $this->assertSame($container->getByType(EchoHandler::class), $apis[1]->getHandler());
        $this->assertFalse($container->hasService('api.handler.1'));
    }

    public function testStatementHandlerIsNotReusedForPlainClass(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                endpoints:
                    - {method: GET, version: 1, package: custom, handler: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('Ahoj')}
                    - {method: GET, version: 1, package: plain, handler: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler}
                    - {method: GET, version: 1, package: plain2, handler: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler}
            NEON);

        $handlers = array_map(self::greeting(...), $container->getByType(ApiDecider::class)->getApis());
        $this->assertSame(['Ahoj', 'Hello', 'Hello'], array_map(static fn (GreetingHandler $handler): string => $handler->greeting, $handlers));
        $this->assertSame($handlers[1], $handlers[2]);
    }

    public function testUnknownHandlerServiceFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Service 'missing' used as @missing in the handler of endpoint GET /v1/users (api.endpoints[0]) does not exist. Register it in the services section, or use a class name instead of @missing.");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: @missing}");
    }

    public function testHandlerThatIsNotHandlerFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Class 'stdClass' in the handler of endpoint GET /v1/users/list (api.endpoints[0]) must implement Tomaj\\NetteApi\\Handlers\\ApiHandlerInterface, but it does not. Extend Tomaj\\NetteApi\\Handlers\\BaseHandler (or implement the interface), or use another class.");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, action: list, handler: stdClass}");
    }

    public function testMissingHandlerClassFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Class 'App\\Missing' in the handler of endpoint GET /v1/users (api.endpoints[0]) does not exist. Check the spelling and the autoload section of composer.json. Use a handler class name, @serviceName, or a statement like App\\Api\\MyHandler(%tempDir%).");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: App\\Missing}");
    }

    public function testAmbiguousHandlerClassFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Class '" . self::FIXTURES . "\\GreetingHandler' in the handler of endpoint GET /v1/users (api.endpoints[0]) is ambiguous, services 'a', 'b' are all autowired instances of it. Use the service name instead of the class, for example handler: @a.");
        ContainerFactory::compile(<<<NEON
            services:
                a: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('A')
                b: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('B')
            api:
                endpoints:
                    - {method: GET, version: 1, package: users, handler: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler}
            NEON);
    }

    public function testDuplicateEndpointFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Endpoint GET /v1/users/list is defined twice: in api.endpoints[0] and in api.endpoints[2]. Remove one of them, or change the method, version, package or action of one so the URLs differ.');
        ContainerFactory::compile(<<<NEON
            api:
                endpoints:
                    - {method: GET, version: 1, package: users, action: list, handler: Tomaj\NetteApi\Handlers\EchoHandler}
                    - {method: POST, version: 1, package: users, action: list, handler: Tomaj\NetteApi\Handlers\EchoHandler}
                    - {method: get, version: '1', package: users, action: list, handler: Tomaj\NetteApi\Handlers\EchoHandler}
            NEON);
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function invalidEndpoints(): \Iterator
    {
        yield 'empty method' => ["{method: ' ', version: 1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid method ' ', a method is one word of letters. Use an HTTP method, for example method: GET."];
        yield 'space in method' => ["{method: 'GE T', version: 1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid method 'GE T',"];
        yield 'slash in version' => ["{method: GET, version: '1/2', package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version '1/2', a version is one URL segment without a slash. Use for example version: '1.1'."];
        yield 'v prefix in version' => ["{method: GET, version: v1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version 'v1', the 'v' is added to the URL automatically (/v1/users). Use for example version: 1."];
        yield 'uppercase v prefix in version' => ["{method: GET, version: V2, package: users, handler: X}", "invalid version 'V2', the 'v' is added to the URL automatically (/v2/users). Use for example version: 2."];
        yield 'v prefix in decimal version' => ["{method: GET, version: 'v1.1', package: users, handler: X}", "the 'v' is added to the URL automatically (/v1.1/users). Use for example version: '1.1'."];
        yield 'empty version' => ["{method: GET, version: '', package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version '', it is empty. Use for example version: 1."];
        yield 'float version' => ["{method: GET, version: 1.5, package: users, handler: X}", "api.endpoints[0].version: 1.5 is read by neon as a decimal number, so for example 1.10 would become 1.1. Write the version in quotes: version: '1.5'."];
        yield 'empty package' => ["{method: GET, version: 1, package: '', handler: X}", "Endpoint in api.endpoints[0] has an invalid package '', it is empty. Use for example package: users."];
        yield 'slash in package' => ["{method: GET, version: 1, package: 'users/admin', handler: X}", "Endpoint in api.endpoints[0] has an invalid package 'users/admin', a package is one URL segment without a slash. Use for example package: users, action: admin."];
        yield 'two slashes in package' => ["{method: GET, version: 1, package: 'a/b/c', handler: X}", "invalid package 'a/b/c', a package is one URL segment without a slash. Use for example package: users."];
        yield 'leading slash in package' => ["{method: GET, version: 1, package: '/users', handler: X}", "invalid package '/users', a package is one URL segment without a slash. Use for example package: users."];
        yield 'trailing slash in package' => ["{method: GET, version: 1, package: 'users/', handler: X}", "invalid package 'users/', a package is one URL segment without a slash. Use for example package: users."];
        yield 'slash in action' => ["{method: GET, version: 1, package: users, action: 'a/b', handler: X}", "Endpoint in api.endpoints[0] has an invalid action 'a/b', an action is one URL segment without a slash. Use for example action: detail."];
    }

    #[DataProvider('invalidEndpoints')]
    public function testInvalidEndpointFails(string $endpoint, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    endpoints:\n        - " . $endpoint);
    }

    public function testAuthorizationsRateLimitsAndDefaults(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                strictLimit:
                    create: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit(1)
                    autowired: false
            api:
                authorizations:
                    basic: Tomaj\NetteApi\Authorization\BasicAuthentication([admin: secret])
                    none: Tomaj\NetteApi\Authorization\NoAuthorization
                rateLimits:
                    normal: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit(100)
                defaults:
                    authorization: basic
                    rateLimit: normal
                endpoints:
                    - {method: GET, version: 1, package: defaults, handler: Tomaj\NetteApi\Handlers\EchoHandler}
                    - {method: GET, version: 1, package: named, handler: Tomaj\NetteApi\Handlers\EchoHandler, authorization: none, rateLimit: @strictLimit}
                    - {method: GET, version: 1, package: 'off', handler: Tomaj\NetteApi\Handlers\EchoHandler, authorization: false, rateLimit: false}
                    - {method: GET, version: 1, package: inline, handler: Tomaj\NetteApi\Handlers\EchoHandler, authorization: Tomaj\NetteApi\Authorization\NoAuthorization, rateLimit: Tomaj\NetteApi\Test\DI\Fixtures\FixedRateLimit(7)}
            NEON);

        $apis = $container->getByType(ApiDecider::class)->getApis();
        $this->assertInstanceOf(BasicAuthentication::class, $apis[0]->getAuthorization());
        $this->assertSame($container->getService('api.authorization.basic'), $apis[0]->getAuthorization());
        $this->assertSame($container->getService('api.rateLimit.normal'), $apis[0]->getRateLimit());
        $rateLimit = $apis[0]->getRateLimit();
        $this->assertInstanceOf(FixedRateLimit::class, $rateLimit);
        $this->assertSame(100, $rateLimit->limit);

        $this->assertSame($container->getService('api.authorization.none'), $apis[1]->getAuthorization());
        $this->assertSame($container->getService('strictLimit'), $apis[1]->getRateLimit());

        $this->assertInstanceOf(NoAuthorization::class, $apis[2]->getAuthorization());
        $this->assertInstanceOf(NoRateLimit::class, $apis[2]->getRateLimit());

        $this->assertInstanceOf(NoAuthorization::class, $apis[3]->getAuthorization());
        $this->assertNotSame($container->getService('api.authorization.none'), $apis[3]->getAuthorization());
        $rateLimit = $apis[3]->getRateLimit();
        $this->assertInstanceOf(FixedRateLimit::class, $rateLimit);
        $this->assertSame(7, $rateLimit->limit);

        $this->assertNull($container->getByType(BasicAuthentication::class, false), 'named services are not autowired');
        $this->assertNull($container->getByType(RateLimiterInterface::class, false));
        $this->assertSame(['strictLimit'], $container->findByType(FixedRateLimit::class));
    }

    public function testUnknownAuthorizationFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Unknown authorization 'bearer' in the authorization of endpoint GET /v1/users (api.endpoints[0]), it is not a name from api.authorizations nor an existing class. Names defined in api.authorizations: 'basic', 'none'. Or use @serviceName, a class implementing Tomaj\\NetteApi\\Authorization\\ApiAuthorizationInterface, or a statement like App\\MyClass(arguments). To add the name, write api: {authorizations: {bearer: App\\Api\\MyAuthorization}}.");
        ContainerFactory::compile(<<<NEON
            api:
                authorizations:
                    basic: Tomaj\NetteApi\Authorization\NoAuthorization
                    none: Tomaj\NetteApi\Authorization\NoAuthorization
                endpoints:
                    - {method: GET, version: 1, package: users, handler: Tomaj\NetteApi\Handlers\EchoHandler, authorization: bearer}
            NEON);
    }

    public function testUnknownDefaultRateLimitFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Unknown rateLimit 'strict' in api.defaults.rateLimit, it is not a name from api.rateLimits nor an existing class. No names are defined in api.rateLimits yet. Or use @serviceName, a class implementing Tomaj\\NetteApi\\RateLimit\\RateLimiterInterface, or a statement like App\\MyClass(arguments). To add the name, write api: {rateLimits: {strict: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}}}.");
        ContainerFactory::compile(<<<NEON
            api:
                defaults: {rateLimit: strict}
                endpoints:
                    - {method: GET, version: 1, package: users, handler: Tomaj\NetteApi\Handlers\EchoHandler}
            NEON);
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function wrongClasses(): \Iterator
    {
        $handler = EchoHandler::class;
        $endpoint = "\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: $handler, %s}";
        yield 'named authorization' => ["authorizations:\n        bearer: $handler", "Class '$handler' in api.authorizations.bearer must implement Tomaj\NetteApi\Authorization\ApiAuthorizationInterface, but it does not. Implement the interface in the class, or use another class."];
        yield 'named rate limit statement' => ["rateLimits:\n        fixed: $handler()", "Class '$handler' in api.rateLimits.fixed must implement Tomaj\NetteApi\RateLimit\RateLimiterInterface, but it does not."];
        yield 'endpoint authorization' => [sprintf($endpoint, "authorization: $handler"), "Class '$handler' in the authorization of endpoint GET /v1/users (api.endpoints[0]) must implement"];
        yield 'default rate limit' => ["defaults:\n        rateLimit: $handler" . sprintf($endpoint, 'action: x'), "Class '$handler' in api.defaults.rateLimit must implement Tomaj\NetteApi\RateLimit\RateLimiterInterface, but it does not."];
        yield 'handler statement' => [sprintf($endpoint, 'action: y') . "\n        - {method: GET, version: 1, package: z, handler: Tomaj\\NetteApi\\Misc\\IpDetector()}", "Class 'Tomaj\\NetteApi\\Misc\\IpDetector' in the handler of endpoint GET /v1/z (api.endpoints[1]) must implement Tomaj\\NetteApi\\Handlers\\ApiHandlerInterface, but it does not. Extend Tomaj\\NetteApi\\Handlers\\BaseHandler (or implement the interface), or use another class."];
        yield 'ip detector' => ["ipDetector: $handler", "Class '$handler' in api.ipDetector must implement Tomaj\NetteApi\Misc\IpDetectorInterface, but it does not."];
        yield 'logger' => ["logger: \\$handler", "Class '$handler' in api.logger must implement"];
        yield 'output configurator' => ["outputConfigurator: $handler", "Class '$handler' in api.outputConfigurator must implement"];
        yield 'error handler' => ["errors:\n        handler: $handler", "Class '$handler' in api.errors.handler must implement Tomaj\NetteApi\Error\ErrorHandlerInterface, but it does not."];
    }

    #[DataProvider('wrongClasses')]
    public function testWrongClassFailsAtCompileTime(string $section, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    " . $section);
    }

    public static function unknownStatementClasses(): iterable
    {
        $missing = "Class 'App\\Nope' in the statement in %s does not exist. Check the spelling and the autoload section of composer.json, the class must implement ";
        yield 'authorization' => ["authorizations:\n        bearer: App\\Nope(1)", sprintf($missing, 'api.authorizations.bearer')];
        yield 'logger' => ['logger: App\\Nope(1)', sprintf($missing, 'api.logger')];
        yield 'handler' => ["endpoints:\n        - {method: GET, version: 1, package: users, handler: App\\Nope(1)}", sprintf($missing, 'the handler of endpoint GET /v1/users (api.endpoints[0])')];
        yield 'endpoint authorization' => ["endpoints:\n        - {method: GET, version: 1, package: users, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler, authorization: App\\Nope(1)}", sprintf($missing, 'the authorization of endpoint GET /v1/users (api.endpoints[0])')];
        yield 'wrong type' => ['logger: stdClass()', "Class 'stdClass' in api.logger must implement Tomaj\\NetteApi\\Logger\\ApiLoggerInterface"];
    }

    #[DataProvider('unknownStatementClasses')]
    public function testStatementWithUnknownClassFails(string $option, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    " . $option);
    }

    public function testReferenceStatementIsLeftToNette(): void
    {
        $container = ContainerFactory::compile("services:\n    auth: Tomaj\\NetteApi\\Authorization\\NoAuthorization\napi:\n    authorizations:\n        bearer: @auth()");
        $this->assertNotNull($container);
    }

    public function testFactoryStatementIsLeftToNette(): void
    {
        $this->expectException(ServiceCreationException::class);
        ContainerFactory::compile("api:\n    logger: App\\Nope::create()");
    }

    public static function invalidValues(): iterable
    {
        $endpoint = "endpoints:\n        - {method: GET, version: %s, package: %s, action: %s, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler}";
        $url = "Endpoint in api.endpoints[0] has an invalid %s '%s', it contains a space, ? or #, which cannot be in a URL path. Use for example %s.";
        yield 'version space' => [sprintf($endpoint, "'1 0'", 'users', 'a'), sprintf($url, 'version', '1 0', 'version: 1')];
        yield 'package query' => [sprintf($endpoint, '1', "'u?x'", 'a'), sprintf($url, 'package', 'u?x', 'package: users')];
        yield 'action hash' => [sprintf($endpoint, '1', 'users', "'a#b'"), sprintf($url, 'action', 'a#b', 'action: detail')];
        $headers = 'Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key';
        $params = 'api_key, apikey, key, token, access_token, refresh_token, id_token, auth, password, secret, signature, sig, code, client_secret';
        yield 'maskHeaders add bad' => ["logging: {maskHeaders: {add: [X-A, 'X Secret']}}", "api.logging.maskHeaders.add[1] 'X Secret': it is not an HTTP header name"];
        yield 'maskHeaders names bad' => ["logging: {maskHeaders: {names: ['X-*']}}", "api.logging.maskHeaders.names[0] 'X-*': it contains *, which is only for header patterns."];
        yield 'maskHeaders remove typo' => ['logging: {maskHeaders: {remove: [Set-Cokie]}}', "api.logging.maskHeaders.remove[0]: 'Set-Cokie' is not in the masked headers ($headers), so there is nothing to remove. Did you mean 'Set-Cookie'? Entries compare case-insensitively; fix the spelling or leave the entry out."];
        yield 'maskHeaders remove by pattern' => ['logging: {maskHeaders: {remove: [X-Foo-Token]}}', "api.logging.maskHeaders.remove[0]: 'X-Foo-Token' is not in the masked headers ($headers), so there is nothing to remove. It is masked by the pattern '*token*'; remove the pattern, or replace the patterns with a narrower list."];
        yield 'maskHeaders remove from names' => ['logging: {maskHeaders: {names: [X-A], remove: [Cookie]}}', "api.logging.maskHeaders.remove[0]: 'Cookie' is not in the masked headers (X-A), so there is nothing to remove."];
        yield 'maskHeaders remove pattern list' => ["logging: {maskHeaders: {remove: [X-Foo-Token], patterns: ['x-*']}}", "is masked by the pattern 'x-*'"];
        yield 'maskHeaders remove patterns off' => ['logging: {maskHeaders: {remove: [X-Foo-Token], patterns: false}}', "api.logging.maskHeaders.remove[0]: 'X-Foo-Token' is not in the masked headers ($headers), so there is nothing to remove. Entries compare"];
        yield 'maskHeaders add and remove' => ['logging: {maskHeaders: {add: [x-a], remove: [X-A]}}', "api.logging.maskHeaders.remove[0] 'X-A' is also in api.logging.maskHeaders.add, so it would be added and removed again. Keep it in one of them."];
        yield 'maskHeaders empty map' => ['logging: {maskHeaders: {add: []}}', 'api.logging.maskHeaders is an empty map, so it changes nothing. Use true for the defaults, false to turn the masking off, or names, add or remove, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}.'];
        yield 'patterns empty map' => ['logging: {maskHeaders: {patterns: {add: []}}}', 'api.logging.maskHeaders.patterns is an empty map, so it changes nothing.'];
        yield 'query empty map' => ['logging: {maskQueryParams: {remove: []}}', 'api.logging.maskQueryParams is an empty map, so it changes nothing.'];
        yield 'maskHeaders names empty' => ['logging: {maskHeaders: {names: []}}', 'api.logging.maskHeaders.names is an empty list. To turn the masking off write false'];
        yield 'maskHeaders remove bad' => ["logging: {maskHeaders: {remove: ['X Y']}}", "api.logging.maskHeaders.remove[0] 'X Y': it is not an HTTP header name"];
        yield 'maskHeaders add and remove case' => ['logging: {maskHeaders: {add: [X-A], remove: [x-A]}}', "api.logging.maskHeaders.remove[0] 'x-A' is also in"];
        yield 'maskHeaders remove after re-add' => ['logging: {maskHeaders: {add: [COOKIE], remove: [Cokie]}}', "'Cokie' is not in the masked headers (Authorization, Proxy-Authorization, COOKIE, Set-Cookie, X-Api-Key), so"];
        yield 'patterns no star' => ['logging: {maskHeaders: {patterns: [token]}}', "api.logging.maskHeaders.patterns[0] 'token': it has no *, so it matches only one header."];
        yield 'patterns add bad' => ["logging: {maskHeaders: {patterns: {add: ['**']}}}", "api.logging.maskHeaders.patterns.add[0] '**': it matches every header"];
        yield 'patterns remove typo' => ["logging: {maskHeaders: {patterns: {remove: ['*tokn*']}}}", "api.logging.maskHeaders.patterns.remove[0]: '*tokn*' is not in the masked header patterns (*token*, *secret*, *key*, x-auth-*), so there is nothing to remove. Did you mean '*token*'?"];
        yield 'patterns empty list' => ['logging: {maskHeaders: {patterns: []}}', 'api.logging.maskHeaders.patterns is an empty list.'];
        yield 'query empty' => ['logging: {maskQueryParams: []}', 'api.logging.maskQueryParams is an empty list. To turn the masking off write false; to change the defaults use add or remove, for example maskQueryParams: {add: [session_id], remove: [code]}.'];
        yield 'query bad' => ["logging: {maskQueryParams: ['a=b']}", "api.logging.maskQueryParams[0] 'a=b': it is not a query parameter name."];
        yield 'query add bad' => ["logging: {maskQueryParams: {add: ['x[]']}}", "api.logging.maskQueryParams.add[0] 'x[]': it is not a query parameter name."];
        yield 'query remove typo' => ['logging: {maskQueryParams: {remove: [cod]}}', "api.logging.maskQueryParams.remove[0]: 'cod' is not in the masked query parameters ($params), so there is nothing to remove. Did you mean 'code'?"];
        yield 'masking typo' => ['logging: {masking: parital}', "api.logging.masking 'parital' is not a masking mode. Did you mean 'partial'? Use 'full', 'partial', 'hash', 'omit', or a class implementing Tomaj\\NetteApi\\Logger\\LogValueMaskerInterface"];
        yield 'hashKey without hash' => ['logging: {masking: partial, hashKey: abc}', "api.logging.hashKey is set, but api.logging.masking is 'partial' and only hash uses the key. Use masking: hash with the key, or remove hashKey."];
        yield 'hashKey default masking' => ['logging: {hashKey: abc}', "api.logging.hashKey is set, but api.logging.masking is 'full'"];
        yield 'hashKey empty' => ["logging: {masking: hash, hashKey: ''}", 'api.logging.hashKey'];
        yield 'masking class missing' => ['logging: {masking: App\\NoMasker}', "Class 'App\\NoMasker' in api.logging.masking does not exist."];
        yield 'maskHeaders empty' => ['logging: {maskHeaders: []}', 'api.logging.maskHeaders is an empty list. To turn the masking off write false; to change the defaults use add or remove, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}.'];
        yield 'maskHeaders bad name' => ["logging: {maskHeaders: [Authorization, 'X Secret']}", "api.logging.maskHeaders[1] 'X Secret': it is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, for example X-Session."];
        yield 'maskHeaders colon' => ["logging: {maskHeaders: ['Authorization:']}", "api.logging.maskHeaders[0] 'Authorization:': it is not an HTTP header name"];
        yield 'preflight empty' => ['cors: {preflight: {}}', 'api.cors.preflight is an empty map, so the preflight response would send no CORS headers. Use preflight: true'];
        yield 'preflight bad name' => ["cors: {preflight: {'Max Age': '600'}}", "api.cors.preflight key 'Max Age' is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, for example preflight: {Access-Control-Max-Age: '600'}."];
        yield 'authorization name' => ["authorizations:\n        'my-auth': Tomaj\\NetteApi\\Authorization\\NoAuthorization", "api.authorizations: the name 'my-auth' is not valid, a name has only letters, digits and underscores. Use for example authorizations: {bearer: App\\Api\\MyAuthorization}."];
        yield 'non-ASCII name' => ["rateLimits:\n        'stríct': Tomaj\\NetteApi\\RateLimit\\NoRateLimit", "api.rateLimits: the name 'stríct' is not valid"];
        yield 'name with a new line' => ["rateLimits:\n        \"strict\\n\": Tomaj\\NetteApi\\RateLimit\\NoRateLimit", "api.rateLimits: the name 'strict\n' is not valid"];
        yield 'rate limit name' => ["rateLimits:\n        '': Tomaj\\NetteApi\\RateLimit\\NoRateLimit", "api.rateLimits: the name '' is not valid, a name has only letters, digits and underscores. Use for example rateLimits: {strict: App\\Api\\MyRateLimit}."];
        yield 'typeBaseUri no scheme' => ['errors: {format: problemDetails, typeBaseUri: example.com/errors}', "api.errors.typeBaseUri 'example.com/errors' is not an absolute URI, the type of a problem is a URI. Use for example typeBaseUri: https://example.com/errors/."];
        yield 'typeBaseUri space' => ["errors: {format: problemDetails, typeBaseUri: 'https://x/a b'}", "api.errors.typeBaseUri 'https://x/a b' is not an absolute URI"];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueFails(string $option, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    " . $option);
    }

    /**
     * Every misconfiguration of the api section, with the full message including the hint on how to fix it.
     */
    public static function neonErrors(): iterable
    {
        yield 'maskHeaders map typo' => ["api:\n    logging: {maskHeaders: {ad: [X-A]}}", "Unexpected item 'api › logging › maskHeaders › ad', did you mean 'add'? Allowed keys in api.logging.maskHeaders: names, add, remove, patterns."];
        yield 'patterns typo' => ["api:\n    logging: {maskHeaders: {patterns: {remvoe: [x]}}}", "Unexpected item 'api › logging › maskHeaders › patterns › remvoe', did you mean 'remove'? Allowed keys in api.logging.maskHeaders.patterns: add, remove."];
        yield 'maskQueryParams typo' => ["api:\n    logging: {maskQueryParams: {patterns: [x]}}", "Unexpected item 'api › logging › maskQueryParams › patterns'. Allowed keys in api.logging.maskQueryParams: names, add, remove."];
        yield 'maskQueryParams type' => ["api:\n    logging: {maskQueryParams: 5}", "The item 'api › logging › maskQueryParams' expects to be bool|list|array, 5 given. Use true (the default), false, a list of parameter names that replaces the defaults, or a map, for example maskQueryParams: {add: [session_id], remove: [code]}."];
        yield 'masking type' => ["api:\n    logging: {masking: 5}", "The item 'api › logging › masking' expects to be string|Nette\\DI\\Definitions\\Statement, 5 given. Use 'full', 'partial', 'hash', 'omit', a class implementing Tomaj\\NetteApi\\Logger\\LogValueMaskerInterface, @serviceName, or a statement like App\\MyClass(arguments)."];
        yield 'hashKey type' => ["api:\n    logging: {masking: hash, hashKey: [a]}", "The item 'api › logging › hashKey' expects to be string|Nette\\DI\\Definitions\\Statement|null, array given. Use a string, for example hashKey: %env.API_LOG_HASH_KEY%."];
        yield 'api scalar' => ['api: 5', 'The item \'api\' expects to be array, 5 given. The api section is a map, for example api: {endpoints: [{method: GET, version: 1, package: users, handler: App\\Api\\UsersHandler}]}.'];
        yield 'top typo' => ['api:
    loger: x', 'Unexpected item \'api › loger\', did you mean \'logger\'? Allowed keys in api: outputConfigurator, ipDetector, logger, errors, cors, logging, authorizations, rateLimits, rateLimiting, defaults, discovery, endpoints.'];
        yield 'errors typo' => ['api:
    errors: {fromat: default}', 'Unexpected item \'api › errors › fromat\', did you mean \'format\'? Allowed keys in api.errors: format, typeBaseUri, handler.'];
        yield 'cors typo' => ['api:
    cors: {mod: off}', 'Unexpected item \'api › cors › mod\', did you mean \'mode\'? Allowed keys in api.cors: mode, allowedOrigins, originValidator, allowCredentials, preflight.'];
        yield 'logging typo' => ['api:
    logging: {mask: true}', 'Unexpected item \'api › logging › mask\'. Allowed keys in api.logging: maskHeaders, maskQueryParams, masking, hashKey.'];
        yield 'defaults typo' => ['api:
    defaults: {authorisation: x}', 'Unexpected item \'api › defaults › authorisation\', did you mean \'authorization\'? Allowed keys in api.defaults: authorization, rateLimit.'];
        yield 'discovery typo' => ['api:
    discovery: {path: [x]}', 'Unexpected item \'api › discovery › path\', did you mean \'paths\'? Allowed keys in api.discovery: paths, namespace.'];
        yield 'endpoint typo' => ['api:
    endpoints:
        - {method: GET, version: 1, pakage: users, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler}', 'Unexpected item \'api › endpoints › 0 › pakage\', did you mean \'package\'? Allowed keys in api.endpoints[0]: method, version, package, action, handler, authorization, rateLimit.'];
        yield 'endpoint missing' => ['api:
    endpoints:
        - {method: GET, version: 1}', 'The mandatory item \'api › endpoints › 0 › package\' is missing. Every endpoint needs method, version, package and handler, for example - {method: GET, version: 1, package: users, handler: App\\Api\\UsersHandler}.'];
        yield 'endpoint scalar' => ['api:
    endpoints:
        - GET', 'The item \'api › endpoints › 0\' expects to be array, \'GET\' given. An endpoint is a map, for example - {method: GET, version: 1, package: users, handler: App\\Api\\UsersHandler}.'];
        yield 'endpoints map' => ['api:
    endpoints: 5', 'The item \'api › endpoints\' expects to be list, 5 given. Use a list of endpoints, for example endpoints: [{method: GET, version: 1, package: users, handler: App\\Api\\UsersHandler}].'];
        yield 'format typo' => ['api:
    errors: {format: problemdetails}', 'The item \'api › errors › format\' expects to be \'default\'|\'problemDetails\'|\'negotiating\'|null, \'problemdetails\' given. Did you mean \'problemDetails\'? Use one of \'default\', \'problemDetails\', \'negotiating\'.'];
        yield 'format bad' => ['api:
    errors: {format: xml}', 'The item \'api › errors › format\' expects to be \'default\'|\'problemDetails\'|\'negotiating\'|null, \'xml\' given. Use one of \'default\', \'problemDetails\', \'negotiating\'.'];
        yield 'typeBaseUri int' => ['api:
    errors: {typeBaseUri: 5}', 'The item \'api › errors › typeBaseUri\' expects to be null or string, 5 given. Use an absolute URI, for example typeBaseUri: https://example.com/errors/.'];
        yield 'errors scalar' => ['api:
    errors: 5', 'The item \'api › errors\' expects to be array, 5 given. errors is a map, for example errors: {format: problemDetails}.'];
        yield 'cors mode int' => ['api:
    cors: {mode: 5}', 'The item \'api › cors › mode\' expects to be null or string, 5 given. Use \'*\', \'off\', \'reflect-unsafe\', or one origin like https://app.example.com.'];
        yield 'cors mode boolean' => ['api:
    cors: {mode: false}', 'The item \'api › cors › mode\' expects to be null or string, false given. Neon read the mode as a boolean (an unquoted off, no, on or yes), write it in quotes, for example mode: \'off\'. Use \'*\', \'off\', \'reflect-unsafe\', or one origin like https://app.example.com.'];
        yield 'cors mode true' => ['api:
    cors: {mode: true}', 'The item \'api › cors › mode\' expects to be null or string, true given. Neon read the mode as a boolean (an unquoted off, no, on or yes), write it in quotes, for example mode: \'off\'. Use \'*\', \'off\', \'reflect-unsafe\', or one origin like https://app.example.com.'];
        yield 'cors mode empty' => ['api:
    cors: {mode: \'\'}', 'api.cors.mode \'\' is not valid. It is empty. Use \'*\', \'off\', \'reflect-unsafe\', or one origin like https://app.example.com (for more origins use api.cors.allowedOrigins).'];
        yield 'origins scalar' => ['api:
    cors: {allowedOrigins: 5}', 'The item \'api › cors › allowedOrigins\' expects to be null or list, 5 given. Use a list, for example allowedOrigins: [https://app.example.com].'];
        yield 'preflight int' => ['api:
    cors: {preflight: 5}', 'The item \'api › cors › preflight\' expects to be bool|array, 5 given. Use true, false, or a map of headers, for example preflight: {Access-Control-Max-Age: \'600\'}.'];
        yield 'preflight value' => ['api:
    cors: {preflight: {X-A: {a: b}}}', 'The item \'api › cors › preflight › X-A\' expects to be string|list, array given. A header value is a string or a list of strings, for example Access-Control-Allow-Headers: [Content-Type, Authorization].'];
        yield 'maskHeaders str' => ['api:
    logging: {maskHeaders: 5}', 'The item \'api › logging › maskHeaders\' expects to be bool|list|array, 5 given. Use true (the default), false, a list of header names that replaces the defaults, or a map, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie], patterns: {add: [x-signature-*]}}.'];
        yield 'auths list' => ['api:
    authorizations: 5', 'The item \'api › authorizations\' expects to be array, 5 given. Use a map of name: class, for example authorizations: {bearer: App\\Api\\MyAuthorization}.'];
        yield 'rate list' => ['api:
    rateLimits: 5', 'The item \'api › rateLimits\' expects to be array, 5 given. Use a map of name: limit, for example rateLimits: {perIp: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}}.'];
        yield 'logger int' => ['api:
    logger: 5', 'The item \'api › logger\' expects to be string|Nette\\DI\\Definitions\\Statement|null, 5 given. Use a class implementing Tomaj\\NetteApi\\Logger\\ApiLoggerInterface, @serviceName, or a statement like App\\MyClass(arguments).'];
        yield 'logger missing service' => ['api:
    logger: @nope', 'Service \'nope\' used as @nope in api.logger does not exist. Register it in the services section, or use a class name instead of @nope.'];
        yield 'logger wrong service' => ['services:
    s: stdClass
api:
    logger: @s', 'Class \'stdClass\' in service \'s\' (api.logger) must implement Tomaj\\NetteApi\\Logger\\ApiLoggerInterface, but it does not. Implement the interface in the class, or use another class.'];
        yield 'ipDetector missing svc' => ['api:
    ipDetector: @nope', 'Service \'nope\' used as @nope in api.ipDetector does not exist. Register it in the services section, or use a class name instead of @nope.'];
        yield 'outputConfigurator svc' => ['api:
    outputConfigurator: @nope', 'Service \'nope\' used as @nope in api.outputConfigurator does not exist. Register it in the services section, or use a class name instead of @nope.'];
        yield 'errors handler svc' => ['api:
    errors: {handler: @nope}', 'Service \'nope\' used as @nope in api.errors.handler does not exist. Register it in the services section, or use a class name instead of @nope.'];
        yield 'auth svc' => ['api:
    authorizations: {bearer: @nope}', 'Service \'nope\' used as @nope in api.authorizations.bearer does not exist. Register it in the services section, or use a class name instead of @nope.'];
        yield 'rate svc' => ['api:
    rateLimits: {s: @nope}', 'Service \'nope\' used as @nope in api.rateLimits.s does not exist. Register it in the services section, or use a class name instead of @nope.'];
        yield 'namespace no paths' => ['api:
    discovery: {namespace: App}', 'api.discovery.namespace \'App\' is set, but api.discovery.paths is empty, and the namespace limits only the classes found in the paths. Add the directories, for example discovery: {paths: [%appDir%/Api], namespace: App}, or remove the namespace.'];
        yield 'paths scalar' => ['api:
    discovery: {paths: 5}', 'The item \'api › discovery › paths\' expects to be list, 5 given. Use a list of directories, for example paths: [%appDir%/Api].'];
        yield 'namespace int' => ['api:
    discovery: {namespace: 5}', 'The item \'api › discovery › namespace\' expects to be null or string, 5 given. Use a namespace, for example namespace: App\\Api.'];
        yield 'method typo' => ['api:
    endpoints:
        - {method: GETT, version: 1, package: users, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler}', 'Endpoint in api.endpoints[0] has method \'GETT\', which looks like a typo. Did you mean \'GET\'? Use an HTTP method (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS), for example method: GET.'];
        yield 'method int' => ['api:
    endpoints:
        - {method: 5, version: 1, package: users, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler}', 'The item \'api › endpoints › 0 › method\' expects to be string, 5 given. Use an HTTP method, for example method: GET.'];
        yield 'version list' => ['api:
    endpoints:
        - {method: GET, version: [1], package: users, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler}', 'The item \'api › endpoints › 0 › version\' expects to be string|int|float, array given. Use a number or a string, for example version: 1.'];
        yield 'handler int' => ['api:
    endpoints:
        - {method: GET, version: 1, package: users, handler: 5}', 'The item \'api › endpoints › 0 › handler\' expects to be string|Nette\\DI\\Definitions\\Statement, 5 given. Use a handler class name, @serviceName, or a statement like App\\MyClass(arguments).'];
        yield 'endpoint auth int' => ['api:
    endpoints:
        - {method: GET, version: 1, package: users, handler: Tomaj\\NetteApi\\Handlers\\EchoHandler, authorization: 5}', 'The item \'api › endpoints › 0 › authorization\' expects to be string|Nette\\DI\\Definitions\\Statement|false|null, 5 given. Use false (no authorization for this endpoint), a name from api.authorizations, a class implementing Tomaj\\NetteApi\\Authorization\\ApiAuthorizationInterface, @serviceName, or a statement like App\\MyClass(arguments).'];
        yield 'format typo of default' => ["api:\n    errors: {format: defalt}", "The item 'api › errors › format' expects to be 'default'|'problemDetails'|'negotiating'|null, 'defalt' given. Did you mean 'default'? Use one of 'default', 'problemDetails', 'negotiating'."];
        yield 'endpoint string close to a format' => ["api:\n    endpoints:\n        - defaul", "The item 'api › endpoints › 0' expects to be array, 'defaul' given. An endpoint is a map, for example - {method: GET, version: 1, package: users, handler: App\\Api\\UsersHandler}."];
        yield 'typeBaseUri scheme not first' => ["api:\n    errors: {format: problemDetails, typeBaseUri: '1http://x/'}", "api.errors.typeBaseUri '1http://x/' is not an absolute URI, the type of a problem is a URI. Use for example typeBaseUri: https://example.com/errors/."];
    }

    #[DataProvider('neonErrors')]
    public function testNeonErrorTellsHowToFix(string $neon, string $message): void
    {
        try {
            ContainerFactory::compile($neon);
            self::fail('The configuration should fail.');
        } catch (InvalidConfigurationException $e) {
            // nette/schema joins the path with non-breaking spaces: 'api › errors'
            $this->assertSame($message, str_replace("\u{a0}", ' ', $e->getMessage()));
        }
    }

    public function testDiscoveryPathThatIsAFileFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(sprintf("api.discovery.paths[0]: '%s' is a file, the paths are directories. Use the directory of the file, for example paths: [%%appDir%%/Api].", __FILE__));
        ContainerFactory::compile("api:\n    discovery: {paths: [" . __FILE__ . "]}");
    }

    public function testValidHeadersAndUriCompile(): void
    {
        $container = ContainerFactory::compile("api:\n    logging: {maskHeaders: [Authorization, X-Api_Key.1]}\n    cors: {preflight: {Access-Control-Max-Age: '600'}}\n    errors: {format: problemDetails, typeBaseUri: 'HTTPS://example.com/errors/'}\n    authorizations:\n        bearer_2: Tomaj\\NetteApi\\Authorization\\NoAuthorization");
        $this->assertNotNull($container);
    }

    public function testEmptyTypeBaseUriCompiles(): void
    {
        // an empty parameter, for example typeBaseUri: %problemTypeBaseUri%, means about:blank like in ProblemDetailsErrorHandler
        $container = ContainerFactory::compile("api:\n    errors: {format: problemDetails, typeBaseUri: ''}");
        $this->assertNotNull($container);
    }

    public function testUnknownNamedServiceFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Class 'App\\Nope' in api.authorizations.bearer does not exist. Check the spelling and the autoload section of composer.json. Use a class implementing Tomaj\\NetteApi\\Authorization\\ApiAuthorizationInterface, @serviceName, or a statement like App\\MyClass(arguments).");
        ContainerFactory::compile("api:\n    authorizations:\n        bearer: App\\Nope");
    }

    /**
     * @return \Iterator<string, array{string, class-string}>
     */
    public static function outputConfigurators(): \Iterator
    {
        yield 'env' => ['env', EnvConfigurator::class];
        yield 'debugger' => ['debugger', DebuggerConfigurator::class];
        yield 'query' => ['query', QueryConfigurator::class];
        yield 'class' => [DebuggerConfigurator::class, DebuggerConfigurator::class];
        yield 'statement' => ['Tomaj\NetteApi\Test\Error\StaticConfigurator(true)', StaticConfigurator::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('outputConfigurators')]
    public function testOutputConfigurator(string $value, string $class): void
    {
        $container = ContainerFactory::create("api:\n    outputConfigurator: " . $value);
        $this->assertInstanceOf($class, $container->getByType(ConfiguratorInterface::class));
        $this->assertSame($container->getService('api.outputConfigurator'), $container->getByType(ConfiguratorInterface::class));
    }

    public function testOutputConfiguratorReference(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                myConfigurator:
                    create: Tomaj\NetteApi\Test\Error\StaticConfigurator(false)
                    autowired: false
            api:
                outputConfigurator: @myConfigurator
            NEON);
        $this->assertSame($container->getService('myConfigurator'), $container->getByType(ConfiguratorInterface::class));
    }

    public function testProblemDetailsErrors(): void
    {
        $container = ContainerFactory::create("api:\n    errors: {format: problemDetails, typeBaseUri: 'https://api.example.com/problems'}");
        $handler = $container->getByType(ErrorHandlerInterface::class);
        $this->assertInstanceOf(ProblemDetailsErrorHandler::class, $handler);
        $this->assertSame('https://api.example.com/problems/', $this->property($handler, 'typeBaseUri'));
        $this->assertSame($container->getByType(ConfiguratorInterface::class), $this->property($handler, 'outputConfigurator'));
    }

    public function testProblemDetailsErrorsWithoutTypeBaseUri(): void
    {
        $container = ContainerFactory::create("api:\n    errors: {format: problemDetails}");
        $handler = $container->getByType(ErrorHandlerInterface::class);
        $this->assertInstanceOf(ProblemDetailsErrorHandler::class, $handler);
        $this->assertNull($this->property($handler, 'typeBaseUri'));
    }

    public function testNegotiatingErrors(): void
    {
        $container = ContainerFactory::create("api:\n    errors: {format: negotiating, typeBaseUri: 'https://api.example.com/problems/'}");
        $handler = $container->getByType(ErrorHandlerInterface::class);
        $this->assertInstanceOf(NegotiatingErrorHandler::class, $handler);
        $this->assertInstanceOf(DefaultErrorHandler::class, $this->property($handler, 'fallback'));
        $problemDetails = $this->property($handler, 'problemDetails');
        $this->assertInstanceOf(ProblemDetailsErrorHandler::class, $problemDetails);
        $this->assertSame('https://api.example.com/problems/', $this->property($problemDetails, 'typeBaseUri'));
    }

    public function testDefaultErrorsFormat(): void
    {
        $container = ContainerFactory::create("api:\n    errors: {format: default}");
        $this->assertInstanceOf(DefaultErrorHandler::class, $container->getByType(ErrorHandlerInterface::class));
    }

    public function testCustomErrorHandler(): void
    {
        $container = ContainerFactory::create("api:\n    errors: {handler: Tomaj\\NetteApi\\Error\\ProblemDetailsErrorHandler(typeBaseUri: 'https://x.test')}");
        $handler = $container->getByType(ErrorHandlerInterface::class);
        $this->assertInstanceOf(ProblemDetailsErrorHandler::class, $handler);
        $this->assertSame('https://x.test/', $this->property($handler, 'typeBaseUri'));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function invalidErrors(): \Iterator
    {
        $both = ', but format and typeBaseUri configure the built-in error handler, which your handler replaces. Remove %s, or remove handler and use only format (and typeBaseUri), for example errors: {format: problemDetails}.';
        $base = "api.errors.typeBaseUri is set, but api.errors.format is %s and typeBaseUri is used only by the 'problemDetails' and 'negotiating' formats. Add format: problemDetails (or negotiating), or remove typeBaseUri.";
        yield 'handler and format' => ['{handler: Tomaj\NetteApi\Error\DefaultErrorHandler, format: default}', 'api.errors.handler is set together with api.errors.format' . sprintf($both, 'format')];
        yield 'handler, format and typeBaseUri' => ['{handler: Tomaj\NetteApi\Error\DefaultErrorHandler, format: problemDetails, typeBaseUri: x}', 'api.errors.handler is set together with api.errors.format' . sprintf($both, 'format')];
        yield 'handler and typeBaseUri' => ['{handler: Tomaj\NetteApi\Error\DefaultErrorHandler, typeBaseUri: x}', 'api.errors.handler is set together with api.errors.typeBaseUri' . sprintf($both, 'typeBaseUri')];
        yield 'typeBaseUri alone' => ['{typeBaseUri: x}', sprintf($base, 'not set')];
        yield 'typeBaseUri with default' => ['{format: default, typeBaseUri: x}', sprintf($base, "'default'")];
    }

    #[DataProvider('invalidErrors')]
    public function testInvalidErrors(string $errors, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    errors: " . $errors);
    }

    public function testCorsMaskingAndPreflight(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                cors:
                    allowedOrigins: [https://app.example.com]
                    allowCredentials: true
                    preflight: true
                logging:
                    maskHeaders: true
            NEON);

        $presenter = $container->getService('api.presenter');
        $this->assertSame('off', $this->property($presenter, 'corsHeader'));
        $allowlist = $this->property($presenter, 'corsAllowlist');
        $this->assertIsObject($allowlist);
        $this->assertSame(['https://app.example.com'], $this->property($allowlist, 'origins'));
        $this->assertTrue($this->property($allowlist, 'allowCredentials'));
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADERS, $presenter->getMaskedLogHeaders());
        $this->assertInstanceOf(CorsPreflightHandler::class, $this->property($container->getByType(ApiDecider::class), 'globalPreflightHandler'));
    }

    public function testCorsWithoutCredentialsAndCustomPreflightHeaders(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                cors:
                    allowedOrigins: [https://app.example.com]
                    preflight:
                        Access-Control-Allow-Headers: [Content-Type]
                        Access-Control-Max-Age: '600'
                logging:
                    maskHeaders: [X-Secret]
            NEON);

        $presenter = $container->getService('api.presenter');
        $this->assertSame('off', $this->property($presenter, 'corsHeader'));
        $allowlist = $this->property($presenter, 'corsAllowlist');
        $this->assertIsObject($allowlist);
        $this->assertFalse($this->property($allowlist, 'allowCredentials'));
        $this->assertSame(['X-Secret'], $presenter->getMaskedLogHeaders());
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADER_PATTERNS, $presenter->getMaskedLogHeaderPatterns(), 'a list replaces only the names');
        $preflight = $this->property($container->getByType(ApiDecider::class), 'globalPreflightHandler');
        $this->assertInstanceOf(CorsPreflightHandler::class, $preflight);
        $this->assertSame(['Access-Control-Allow-Headers' => ['Content-Type'], 'Access-Control-Max-Age' => '600'], $this->property($preflight, 'headers'));
    }

    public function testOwnPresenterKeepsItsCorsHeaderWhenModeIsNotSet(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                withMode:
                    create: Tomaj\NetteApi\Presenters\ApiPresenter
                    setup:
                        - @http.request::getUrl()
                        - setCorsHeader('*')
            api:
            NEON);
        $this->assertSame('*', $this->property($container->getService('withMode'), 'corsHeader'));

        $container = ContainerFactory::create("services:\n    plain: Tomaj\\NetteApi\\Presenters\\ApiPresenter\napi:");
        $this->assertSame('off', $this->property($container->getService('plain'), 'corsHeader'));
    }

    public function testApiMappingIsAdded(): void
    {
        $container = ContainerFactory::create(self::ROUTER . "application:\n    mapping: {Front: App\\*Presenter}\napi:", application: true);
        $factory = self::presenterFactory($container);
        $this->assertSame(ApiPresenter::class, $factory->formatPresenterClass('Api:Api'));
        $this->assertSame('App\\HomePresenter', $factory->formatPresenterClass('Front:Home'));

        $container = ContainerFactory::create(self::ROUTER . "application:\n    aliases: {Api: 'Front:Home'}\napi:", application: true);
        $this->assertSame(ApiPresenter::class, self::presenterFactory($container)->formatPresenterClass('Api:Api'));
    }

    public function testOwnApiMappingIsKept(): void
    {
        $container = ContainerFactory::create(self::ROUTER . "application:\n    mapping: {Api: App\\Api\\*Presenter}\napi:", application: true);
        $this->assertSame('App\\Api\\ApiPresenter', self::presenterFactory($container)->formatPresenterClass('Api:Api'));

        $container = ContainerFactory::create(self::ROUTER . "application:\n    mapping: {Api: Tomaj\\NetteApi\\Presenters\\*Presenter}\napi:", application: true);
        $this->assertSame(ApiPresenter::class, self::presenterFactory($container)->formatPresenterClass('Api:Api'));
    }

    public function testCorsModeAlone(): void
    {
        $container = ContainerFactory::create("api:\n    cors: {mode: 'off'}");
        $this->assertSame('off', $this->property($container->getService('api.presenter'), 'corsHeader'));
    }

    public function testCorsModeReflectUnsafe(): void
    {
        $container = ContainerFactory::create("api:\n    cors: {mode: reflect-unsafe}");
        $this->assertSame('reflect-unsafe', $this->property($container->getService('api.presenter'), 'corsHeader'));
    }

    public function testCredentialsWithoutOriginsFail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('api.cors.allowCredentials: true needs api.cors.allowedOrigins or api.cors.originValidator, credentials are allowed only for origins you allow. Add the origins, for example cors: {allowedOrigins: [https://app.example.com], allowCredentials: true}, or a validator, for example cors: {originValidator: App\\Api\\CustomerOrigins, allowCredentials: true}.');
        ContainerFactory::compile("api:\n    cors: {allowCredentials: true}");
    }

    public function testIpDetectorAndLogger(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                ipDetector: Tomaj\NetteApi\Misc\StaticIpDetector('10.0.0.1')
                logger: Tomaj\NetteApi\Test\DI\Fixtures\MemoryLogger
            NEON);

        $this->assertInstanceOf(StaticIpDetector::class, $container->getByType(IpDetectorInterface::class));
        $this->assertSame('10.0.0.1', $container->getByType(IpDetectorInterface::class)->getRequestIp());
        $this->assertInstanceOf(MemoryLogger::class, $container->getByType(ApiLoggerInterface::class));
        $this->assertSame($container->getService('api.logger'), $container->getByType(ApiLoggerInterface::class));
    }

    public function testServicesFromServicesSectionWin(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                apiDecider:
                    create: Tomaj\NetteApi\ApiDecider
                    setup:
                        - addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'manual'), Tomaj\NetteApi\Handlers\EchoHandler(), Tomaj\NetteApi\Authorization\NoAuthorization())
                apiPresenter: Tomaj\NetteApi\Presenters\ApiPresenter
                configurator: Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator
                errorHandler: Tomaj\NetteApi\Error\ProblemDetailsErrorHandler
                ipDetector: Tomaj\NetteApi\Misc\StaticIpDetector('127.0.0.1')
            api:
                cors: {mode: '*'}
                endpoints:
                    - {method: GET, version: 1, package: neon, handler: Tomaj\NetteApi\Handlers\EchoHandler}
            NEON);

        foreach (['decider', 'presenter', 'outputConfigurator', 'errorHandler', 'ipDetector'] as $name) {
            $this->assertFalse($container->hasService('api.' . $name), $name);
        }

        $decider = $container->getByType(ApiDecider::class);
        $this->assertSame($container->getService('apiDecider'), $decider);
        $this->assertSame(['v1/manual/', 'v1/neon/'], array_map(static fn (Api $api): string => $api->getEndpoint()->getUrl(), $decider->getApis()));
        $presenter = $container->getService('apiPresenter');
        $this->assertInstanceOf(ApiPresenter::class, $presenter);
        $this->assertSame('*', $this->property($presenter, 'corsHeader'));
        $this->assertSame($container->getService('errorHandler'), $container->getByType(ErrorHandlerInterface::class));
        $this->assertSame($container->getService('configurator'), $container->getByType(ConfiguratorInterface::class));
        $this->assertSame($container->getService('ipDetector'), $container->getByType(IpDetectorInterface::class));
    }

    /**
     * @return \Iterator<string, array{string, string, string}>
     */
    public static function conflictingServices(): \Iterator
    {
        yield 'errors' => [DefaultErrorHandler::class, 'errors: {format: problemDetails}', "api.errors is set, but service 'mine' of type Tomaj\\NetteApi\\Error\\ErrorHandlerInterface is registered in the services section too, and the api extension does not know which one to use. Remove api.errors, or remove the service 'mine'."];
        yield 'errors handler' => [DefaultErrorHandler::class, 'errors: {handler: Tomaj\NetteApi\Error\DefaultErrorHandler}', 'api.errors is set, but'];
        yield 'errors typeBaseUri' => [DefaultErrorHandler::class, 'errors: {format: negotiating, typeBaseUri: "https://x/"}', 'api.errors is set, but'];
        yield 'output configurator' => [DebuggerConfigurator::class, 'outputConfigurator: env', "api.outputConfigurator is set, but service 'mine' of type Tomaj\\NetteApi\\Output\\Configurator\\ConfiguratorInterface"];
        yield 'logger' => [MemoryLogger::class, 'logger: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\MemoryLogger', "api.logger is set, but service 'mine' of type Tomaj\\NetteApi\\Logger\\ApiLoggerInterface is registered in the services section too"];
        yield 'ip detector' => [IpDetector::class, 'ipDetector: Tomaj\NetteApi\Misc\IpDetector', "api.ipDetector is set, but service 'mine' of type Tomaj\\NetteApi\\Misc\\IpDetectorInterface"];
    }

    #[DataProvider('conflictingServices')]
    public function testOptionAndOwnServiceConflict(string $service, string $option, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("services:\n    mine: " . $service . "\napi:\n    " . $option);
    }

    public function testTwoOwnServicesOfOneTypeFail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Services 'one', 'two' are all of type Tomaj\\NetteApi\\ApiDecider, and the api extension does not know which one to use. Keep only one of them (autowired: false does not help, the extension looks for every service of the type).");
        ContainerFactory::compile(<<<NEON
            services:
                one: Tomaj\NetteApi\ApiDecider
                two:
                    create: Tomaj\NetteApi\ApiDecider
                    autowired: false
            api:
            NEON);
    }

    public function testOwnDeciderMustBeServiceDefinition(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Service 'deciderFactory' must be a plain service (with factory: or create:), because the api extension adds setup calls to it. Define it for example as deciderFactory: Tomaj\\NetteApi\\ApiDecider.");
        ContainerFactory::compile(<<<NEON
            services:
                deciderFactory:
                    type: Tomaj\NetteApi\ApiDecider
                    imported: true
            api:
            NEON);
    }

    public function testAddEndpointFromAnotherSource(): void
    {
        $extension = new NetteApiExtension();
        $extension->addEndpoint(new EndpointDefinition('GET', 3, 'extra', null, EchoHandler::class, null, null, 'a test'));
        $container = ContainerFactory::create('api:', $extension);
        $this->assertSame(['v3/extra/'], array_map(static fn (Api $api): string => $api->getEndpoint()->getUrl(), $container->getByType(ApiDecider::class)->getApis()));
    }

    public function testPresenterHandlesRequestEndToEnd(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                outputConfigurator: debugger
                logger: Tomaj\NetteApi\Test\DI\Fixtures\MemoryLogger
                endpoints:
                    - {method: GET, version: 1, package: hello, handler: Tomaj\NetteApi\Test\DI\Fixtures\GreetingHandler('Hi')}
            NEON);

        $presenter = $container->getService('api.presenter');
        $this->assertInstanceOf(ApiPresenter::class, $presenter);
        $response = $presenter->run(new Request('Api:Api:default', 'GET', ['version' => '1', 'package' => 'hello', 'apiAction' => null]));
        $this->assertInstanceOf(JsonApiResponse::class, $response);
        $this->assertSame(['message' => 'Hi'], $response->getPayload());
        $logger = $container->getByType(ApiLoggerInterface::class);
        $this->assertInstanceOf(MemoryLogger::class, $logger);
        $this->assertSame([200], $logger->codes);
    }

    private static function presenterFactory(Container $container): PresenterFactory
    {
        $factory = $container->getService('application.presenterFactory');
        self::assertInstanceOf(PresenterFactory::class, $factory);
        return $factory;
    }

    private static function greeting(Api $api): GreetingHandler
    {
        $handler = $api->getHandler();
        self::assertInstanceOf(GreetingHandler::class, $handler);
        return $handler;
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidCors(): iterable
    {
        yield 'renamed auto' => ["{mode: auto}", "The CORS mode 'auto' was renamed to 'reflect-unsafe' in 4.0. It is unsafe: it sends the origin of the Referer header back in Access-Control-Allow-Origin with Access-Control-Allow-Credentials: true, so any website can make requests with the user's cookies and read the responses. Safe replacement: list the origins that call the API, for example cors: {allowedOrigins: [https://app.example.com], allowCredentials: true}; set allowCredentials only when the browser sends cookies or uses withCredentials. To keep the old behaviour anyway, use cors: {mode: reflect-unsafe}. See UPGRADE-4.0.md."];
        yield 'renamed auto uppercase' => ["{mode: Auto}", "The CORS mode 'auto' was renamed to 'reflect-unsafe' in 4.0."];
        yield 'unknown mode' => ["{mode: of}", 'api.cors.mode \'of\' is not valid. Did you mean \'off\'? Use \'*\', \'off\', \'reflect-unsafe\', or one origin like https://app.example.com (for more origins use api.cors.allowedOrigins).'];
        yield 'mode with a path' => ["{mode: 'https://app.example.com/'}", 'api.cors.mode \'https://app.example.com/\' is not valid. Use \'*\', \'off\', \'reflect-unsafe\', or one origin like https://app.example.com (for more origins use api.cors.allowedOrigins).'];
        yield 'mode and origins' => ["{mode: '*', allowedOrigins: [https://app.example.com]}", 'api.cors.mode \'*\' and api.cors.allowedOrigins are both set, but allowedOrigins replaces mode, so mode does nothing. Remove mode.'];
        yield 'validator and mode' => ["{mode: '*', originValidator: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\CustomerOrigins}", 'api.cors.originValidator and api.cors.mode are both set, but only one of them can decide which origins are allowed. Remove mode, the validator replaces it.'];
        yield 'validator and origins' => ["{allowedOrigins: [https://app.example.com], originValidator: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\CustomerOrigins}", 'api.cors.originValidator and api.cors.allowedOrigins are both set, but only one of them can decide which origins are allowed. Remove allowedOrigins and allow the fixed origins in the validator too, for example return in_array($origin, $fixedOrigins, true) || ...;'];
        yield 'validator not implementing' => ["{originValidator: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\MemoryLogger}", "Class 'Tomaj\\NetteApi\\Test\\DI\\Fixtures\\MemoryLogger' in api.cors.originValidator must implement Tomaj\\NetteApi\\Misc\\CorsOriginValidatorInterface, but it does not. Implement the interface in the class, or use another class."];
        yield 'validator invokable' => ["{originValidator: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\InvokableOrigins}", "Class 'Tomaj\\NetteApi\\Test\\DI\\Fixtures\\InvokableOrigins' in api.cors.originValidator must implement Tomaj\\NetteApi\\Misc\\CorsOriginValidatorInterface, but it does not. The class has __invoke(), but the extension needs the interface: add implements CorsOriginValidatorInterface with isAllowedOrigin(string \$origin): bool, or pass the callable to ApiPresenter::setCorsOriginValidator() in PHP."];
        yield 'validator missing class' => ["{originValidator: App\\Api\\CustomerOrigins}", "Class 'App\\Api\\CustomerOrigins' in api.cors.originValidator does not exist. Check the spelling and the autoload section of composer.json. Use a class implementing Tomaj\\NetteApi\\Misc\\CorsOriginValidatorInterface, @serviceName, or a statement like App\\MyClass(arguments)."];
        yield 'validator quoted reference' => ["{originValidator: '@origins'}", "'@origins' in api.cors.originValidator is quoted, and in neon a quoted '@name' is a plain string, not a service reference. Write it without quotes: @origins."];
        yield 'validator missing service' => ["{originValidator: @origins}", "Service 'origins' used as @origins in api.cors.originValidator does not exist."];
        yield 'empty origins' => ["{allowedOrigins: []}", 'api.cors.allowedOrigins is an empty list, so no origin could call the API. Add the origins, for example allowedOrigins: [https://app.example.com], or remove the option.'];
        yield 'origin with a path' => ["{allowedOrigins: [https://a.example.com, 'https://app.example.com/']}", 'api.cors.allowedOrigins[1] \'https://app.example.com/\' is not an origin, it would never match the Origin header a browser sends. Write the scheme and host (and port) without a path or trailing slash, for example https://app.example.com or \'http://localhost:3000\'.'];
    }

    #[DataProvider('provideInvalidCors')]
    public function testInvalidCorsFails(string $cors, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    cors: $cors");
    }

    public function testCorsOriginValidator(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                cors:
                    originValidator: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\CustomerOrigins('https://shop.example.com')
                    allowCredentials: true
            NEON);

        $policy = $this->property($container->getService('api.presenter'), 'corsAllowlist');
        $this->assertInstanceOf(CorsOriginCallback::class, $policy);
        $this->assertTrue($this->property($policy, 'allowCredentials'));
        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://shop.example.com', 'Access-Control-Allow-Credentials' => 'true'], $policy->headers('https://shop.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $policy->headers('https://customer.example.com'));
    }

    public function testCorsOriginValidatorService(): void
    {
        $container = ContainerFactory::create(<<<NEON
            services:
                origins: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\CustomerOrigins
            api:
                cors:
                    originValidator: @origins
            NEON);

        $policy = $this->property($container->getService('api.presenter'), 'corsAllowlist');
        $this->assertInstanceOf(CorsOriginCallback::class, $policy);
        $this->assertFalse($this->property($policy, 'allowCredentials'));
        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://customer.example.com'], $policy->headers('https://customer.example.com'));
    }

    public function testCorsOriginValidatorSchemaError(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("5 given. Use a class implementing Tomaj\\NetteApi\\Misc\\CorsOriginValidatorInterface, @serviceName, or a statement like App\\MyClass(arguments).");
        ContainerFactory::compile("api:\n    cors: {originValidator: 5}");
    }

    public function testCorsModeWithOneOrigin(): void
    {
        $container = ContainerFactory::create("api:\n    cors: {mode: 'https://app.example.com'}");
        $this->assertSame('https://app.example.com', $this->property($container->getService('api.presenter'), 'corsHeader'));
    }

    public function testReferenceToServiceOfWrongTypeFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Class \'stdClass\' in service \'std\' (the handler of endpoint GET /v1/users (api.endpoints[0])) must implement Tomaj\\NetteApi\\Handlers\\ApiHandlerInterface, but it does not. Extend Tomaj\\NetteApi\\Handlers\\BaseHandler (or implement the interface), or use another class.');
        ContainerFactory::compile("services:\n    std: stdClass\napi:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: @std}");
    }

    public function testMisspelledReferenceSuggestsService(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Service \'greting\' used as @greting in the handler of endpoint GET /v1/users (api.endpoints[0]) does not exist. Did you mean \'greeting\'? Register it in the services section, or use a class name instead of @greting.');
        ContainerFactory::compile("services:\n    greeting: " . self::FIXTURES . "\\GreetingHandler\napi:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: @greting}");
    }

    public function testMisspelledNameSuggestsDefinedName(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown authorization \'basci\' in api.defaults.authorization, it is not a name from api.authorizations nor an existing class. Did you mean \'basic\'? Names defined in api.authorizations: \'basic\'. Or use @serviceName, a class implementing Tomaj\\NetteApi\\Authorization\\ApiAuthorizationInterface, or a statement like App\\MyClass(arguments). To add the name, write api: {authorizations: {basci: App\\Api\\MyAuthorization}}.');
        ContainerFactory::compile("api:\n    authorizations: {basic: Tomaj\\NetteApi\\Authorization\\NoAuthorization}\n    defaults: {authorization: basci}");
    }

    public function testQuotedReferenceInServiceOptionFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("'@auth' in api.defaults.authorization is quoted, and in neon a quoted '@name' is a plain string, not a service reference. Write it without quotes: @auth.");
        ContainerFactory::compile("api:\n    defaults: {authorization: '@auth'}");
    }

    private function property(object $object, string $name): mixed
    {
        return new ReflectionProperty($object, $name)->getValue($object);
    }

    public function testLoggingMaskingForms(): void
    {
        $presenter = static fn (string $neon): ApiPresenter => ContainerFactory::create("api:\n    logging: " . $neon)->getService('api.presenter');

        $p = $presenter('{maskHeaders: false, maskQueryParams: false}');
        $this->assertSame([[], [], []], [$p->getMaskedLogHeaders(), $p->getMaskedLogHeaderPatterns(), $p->getMaskedLogQueryParams()]);

        $p = $presenter('{maskHeaders: {add: [X-Session], remove: [set-cookie], patterns: {add: [x-signature-*], remove: [*KEY*]}}, maskQueryParams: {add: [session_id], remove: [code]}}');
        $this->assertSame(['Authorization', 'Proxy-Authorization', 'Cookie', 'X-Api-Key', 'X-Session'], $p->getMaskedLogHeaders());
        $this->assertSame(['*token*', '*secret*', 'x-auth-*', 'x-signature-*'], $p->getMaskedLogHeaderPatterns());
        $this->assertSame(['api_key', 'apikey', 'key', 'token', 'access_token', 'refresh_token', 'id_token', 'auth', 'password', 'secret', 'signature', 'sig', 'client_secret', 'session_id'], $p->getMaskedLogQueryParams());

        $p = $presenter('{maskHeaders: {names: [X-A], add: [X-B], patterns: false}, maskQueryParams: [a, b]}');
        $this->assertSame(['X-A', 'X-B'], $p->getMaskedLogHeaders());
        $this->assertSame([], $p->getMaskedLogHeaderPatterns());
        $this->assertSame(['a', 'b'], $p->getMaskedLogQueryParams());

        $p = $presenter('{maskHeaders: {patterns: [x-*]}, maskQueryParams: {names: [a], remove: [a]}}');
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADERS, $p->getMaskedLogHeaders());
        $this->assertSame(['x-*'], $p->getMaskedLogHeaderPatterns());
        $this->assertSame([], $p->getMaskedLogQueryParams());

        $p = $presenter('{maskHeaders: {names: [X-A], remove: [X-A]}}');
        $this->assertSame([], $p->getMaskedLogHeaders());
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADER_PATTERNS, $p->getMaskedLogHeaderPatterns(), 'removing the last name keeps the patterns');

        $p = $presenter('{maskHeaders: {patterns: false}}');
        $this->assertSame(ApiPresenter::DEFAULT_MASKED_LOG_HEADERS, $p->getMaskedLogHeaders());
        $this->assertSame([], $p->getMaskedLogHeaderPatterns());
    }

    public function testLoggingMaskingModes(): void
    {
        $masker = static fn (string $neon): LogValueMaskerInterface => ContainerFactory::create("api:\n    logging: " . $neon)->getService('api.presenter')->getLogMasking();

        foreach (LogMaskMode::cases() as $mode) {
            $m = $masker('{masking: ' . $mode->value . '}');
            $this->assertInstanceOf(LogValueMasker::class, $m);
            $this->assertSame($mode, $m->getMode());
        }
        $this->assertSame('hash:' . substr(hash_hmac('sha256', 'v', 'k3y'), 0, 16), $masker('{masking: hash, hashKey: k3y}')->mask('v'));
        $this->assertSame('hash:' . substr(hash('sha256', 'v'), 0, 16), $masker('{masking: hash}')->mask('v'));
        $this->assertInstanceOf(UpperMasker::class, $masker('{masking: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\UpperMasker}'));
        $this->assertSame('[X]', $masker("{masking: Tomaj\\NetteApi\\Test\\DI\\Fixtures\\UpperMasker('[%s]')}")->mask('x'));
    }


    /**
     * The extension adds its calls after the presenter's setup, so a key that would replace the setup fails instead
     */
    #[DataProvider('maskingSetupConflicts')]
    public function testMaskingSetupAndLoggingKeyConflict(string $setup, string $logging, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("services:\n    own:\n        create: Tomaj\\NetteApi\\Presenters\\ApiPresenter\n        setup:\n            - @http.request::getUrl()\n            - " . $setup . "\napi:\n    logging: " . $logging);
    }

    public static function maskingSetupConflicts(): iterable
    {
        $presenter = ApiPresenter::class;
        foreach (['setMaskedLogHeaders([])', 'addMaskedLogHeader(X-A)', 'removeMaskedLogHeader(Cookie)', 'setMaskedLogHeaderPatterns([])', "addMaskedLogHeaderPattern('x-*')", "removeMaskedLogHeaderPattern('*key*')"] as $call) {
            yield $call => [$call, '{maskHeaders: false}', 'api.logging.maskHeaders and the setup of the ' . $presenter . ' service call ' . strstr($call, '(', true) . '() both change the masked headers. The extension runs after the setup and would replace it. Keep one place: move the setup call into api.logging.maskHeaders (for example addMaskedLogHeader(X-Session) is maskHeaders: {add: [X-Session]}), or leave api.logging.maskHeaders out.'];
        }
        foreach (['setMaskedLogQueryParams([])', 'addMaskedLogQueryParam(sid)', 'removeMaskedLogQueryParam(code)'] as $call) {
            yield $call => [$call, '{maskQueryParams: [a]}', 'api.logging.maskQueryParams and the setup of the ' . $presenter . ' service call ' . strstr($call, '(', true) . '() both change the masked query parameters. The extension runs after the setup and would replace it. Keep one place: move the setup call into api.logging.maskQueryParams (for example addMaskedLogQueryParam(session_id) is maskQueryParams: {add: [session_id]}), or leave api.logging.maskQueryParams out.'];
        }
        yield 'setLogMasking' => ['setLogMasking(Tomaj\\NetteApi\\Logger\\LogMaskMode::Omit)', '{masking: hash}', 'api.logging.masking and the setup of the ' . $presenter . ' service call setLogMasking() both change the masking mode. The extension runs after the setup and would replace it. Keep one place: move the setup call into api.logging.masking (for example setLogMasking(Tomaj\\NetteApi\\Logger\\LogMaskMode::Partial) is masking: partial), or leave api.logging.masking out.'];
    }

    public function testMaskingSetupWorksWithOtherOrDefaultLoggingKeys(): void
    {
        $container = ContainerFactory::create(<<<'NEON'
            services:
                own:
                    create: Tomaj\NetteApi\Presenters\ApiPresenter
                    setup:
                        - @http.request::getUrl()
                        - addMaskedLogHeader(X-A)
                        - addMaskedLogQueryParam(sid)
                        - setLogMasking(Tomaj\NetteApi\Logger\LogMaskMode::Omit)
            api:
                logging: {maskHeaders: true, maskQueryParams: true, masking: full}
            NEON);
        $presenter = $container->getService('own');
        $this->assertContains('X-A', $presenter->getMaskedLogHeaders());
        $this->assertContains('sid', $presenter->getMaskedLogQueryParams());
        $this->assertSame(LogMaskMode::Omit, $presenter->getLogMasking()->getMode());

        $container = ContainerFactory::create(<<<'NEON'
            services:
                own:
                    create: Tomaj\NetteApi\Presenters\ApiPresenter
                    setup:
                        - addMaskedLogHeader(X-A)
                        - setLogMasking(Tomaj\NetteApi\Logger\LogMaskMode::Omit)
            api:
                logging: {maskQueryParams: [a]}
            NEON);
        $presenter = $container->getService('own');
        $this->assertContains('X-A', $presenter->getMaskedLogHeaders(), 'only the keys that are set must not meet the setup');
        $this->assertSame(['a'], $presenter->getMaskedLogQueryParams());
    }

    public function testDefaultMaskingAddsNoSetup(): void
    {
        $this->assertStringNotContainsString('setLogMasking', ContainerFactory::compile("api:\n    logging: {masking: full}"));
        $this->assertStringNotContainsString('setMaskedLog', ContainerFactory::compile('api: {}'), 'the defaults live in the presenter');
        $this->assertStringContainsString('setLogMasking', ContainerFactory::compile("api:\n    logging: {masking: omit}"));
    }
}
