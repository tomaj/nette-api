<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Tomaj\NetteApi\RateLimit\RateLimitInterface;
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
        $this->assertSame([], $this->property($presenter, 'maskedLogHeaders'));
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
        $this->expectExceptionMessage("Handler of endpoint GET /v1/users (api.endpoints[0]): service 'missing' does not exist.");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: @missing}");
    }

    public function testHandlerThatIsNotHandlerFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Handler of endpoint GET /v1/users/list (api.endpoints[0]): 'stdClass' is not a class implementing Tomaj\\NetteApi\\Handlers\\ApiHandlerInterface.");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, action: list, handler: stdClass}");
    }

    public function testMissingHandlerClassFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("'App\\Missing' is not a class implementing");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: App\\Missing}");
    }

    public function testAmbiguousHandlerClassFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Handler of endpoint GET /v1/users (api.endpoints[0]): services 'a', 'b' are all of class " . self::FIXTURES . "\\GreetingHandler. Use '@serviceName' as the handler.");
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
        $this->expectExceptionMessage('Endpoint GET /v1/users/list is defined twice: in api.endpoints[0] and in api.endpoints[2]. Keep only one of them.');
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
        yield 'empty method' => ["{method: ' ', version: 1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid method ' '. Use an HTTP method like GET or POST."];
        yield 'space in method' => ["{method: 'GE T', version: 1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid method 'GE T'."];
        yield 'slash in version' => ["{method: GET, version: '1/2', package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version '1/2'. Use a version without a slash and without the 'v' prefix, for example 1 or '1.1'."];
        yield 'v prefix in version' => ["{method: GET, version: v1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version 'v1'."];
        yield 'uppercase v prefix in version' => ["{method: GET, version: V1, package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version 'V1'."];
        yield 'empty version' => ["{method: GET, version: '', package: users, handler: X}", "Endpoint in api.endpoints[0] has an invalid version ''."];
        yield 'float version' => ["{method: GET, version: 1.5, package: users, handler: X}", "Version 1.5 in api.endpoints[0] is a number with a decimal point, write it in quotes: version: '1.5'."];
        yield 'empty package' => ["{method: GET, version: 1, package: '', handler: X}", "Endpoint in api.endpoints[0] has an invalid package ''. Use a package without a slash, for example users."];
        yield 'slash in package' => ["{method: GET, version: 1, package: 'users/admin', handler: X}", "Endpoint in api.endpoints[0] has an invalid package 'users/admin'."];
        yield 'slash in action' => ["{method: GET, version: 1, package: users, action: 'a/b', handler: X}", "Endpoint in api.endpoints[0] has an invalid action 'a/b'. Use an action without a slash, for example detail."];
    }

    #[DataProvider('invalidEndpoints')]
    public function testInvalidEndpointFails(string $endpoint, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    endpoints:\n        - " . $endpoint);
    }

    public function testMissingEndpointKeyFailsSchema(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches("~The mandatory item 'api\\W+endpoints\\W+0\\W+handler' is missing~");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users}");
    }

    public function testUnknownOptionFailsSchema(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches("~Unexpected item 'api\\W+cors\\W+origins'~");
        ContainerFactory::compile("api:\n    cors: {origins: [x]}");
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
        $this->assertNull($container->getByType(RateLimitInterface::class, false));
        $this->assertSame(['strictLimit'], $container->findByType(FixedRateLimit::class));
    }

    public function testUnknownAuthorizationFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Unknown value 'bearer' in the authorization of endpoint GET /v1/users (api.endpoints[0]). Use a name defined in the api section, @serviceName, a class name or a statement like App\\MyClass(arguments). Defined names: 'basic', 'none'.");
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
        $this->expectExceptionMessage("Unknown value 'strict' in the rateLimit of endpoint GET /v1/users (api.endpoints[0]). Use a name defined in the api section, @serviceName, a class name or a statement like App\\MyClass(arguments).");
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
        yield 'named authorization' => ["authorizations:\n        bearer: $handler", "Class '$handler' in api.authorizations.bearer must implement Tomaj\NetteApi\Authorization\ApiAuthorizationInterface."];
        yield 'named rate limit statement' => ["rateLimits:\n        fixed: $handler()", "Class '$handler' in api.rateLimits.fixed must implement Tomaj\NetteApi\RateLimit\RateLimitInterface."];
        yield 'endpoint authorization' => [sprintf($endpoint, "authorization: $handler"), "Class '$handler' in the authorization of endpoint GET /v1/users (api.endpoints[0]) must implement"];
        yield 'default rate limit' => ["defaults:\n        rateLimit: $handler" . sprintf($endpoint, 'action: x'), "Class '$handler' in the rateLimit of endpoint GET /v1/users/x (api.endpoints[0]) must implement Tomaj\NetteApi\RateLimit\RateLimitInterface."];
        yield 'handler statement' => [sprintf($endpoint, 'action: y') . "\n        - {method: GET, version: 1, package: z, handler: Tomaj\\NetteApi\\Misc\\IpDetector()}", "Class 'Tomaj\\NetteApi\\Misc\\IpDetector' in the handler of endpoint GET /v1/z (api.endpoints[1]) must implement Tomaj\\NetteApi\\Handlers\\ApiHandlerInterface."];
        yield 'ip detector' => ["ipDetector: $handler", "Class '$handler' in api.ipDetector must implement Tomaj\NetteApi\Misc\IpDetectorInterface."];
        yield 'logger' => ["logger: \\$handler", "Class '$handler' in api.logger must implement"];
        yield 'output configurator' => ["outputConfigurator: $handler", "Class '$handler' in api.outputConfigurator must implement"];
        yield 'error handler' => ["errors:\n        handler: $handler", "Class '$handler' in api.errors.handler must implement Tomaj\NetteApi\Error\ErrorHandlerInterface."];
    }

    #[DataProvider('wrongClasses')]
    public function testWrongClassFailsAtCompileTime(string $section, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    " . $section);
    }

    public function testStatementWithUnknownClassKeepsNetteError(): void
    {
        $this->expectException(ServiceCreationException::class);
        $this->expectExceptionMessage("Class 'App\\Nope' not found.");
        ContainerFactory::compile("api:\n    authorizations:\n        bearer: App\\Nope(1)");
    }

    public function testHandlerStatementWithUnknownClassKeepsNetteError(): void
    {
        $this->expectException(ServiceCreationException::class);
        $this->expectExceptionMessage("Class 'App\\Nope' not found.");
        ContainerFactory::compile("api:\n    endpoints:\n        - {method: GET, version: 1, package: users, handler: App\\Nope(1)}");
    }

    public function testUnknownNamedServiceFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Unknown value 'App\\Nope' in api.authorizations.bearer.");
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
        $both = 'Use either api.errors.handler, or api.errors.format with api.errors.typeBaseUri, not both.';
        $base = "api.errors.typeBaseUri is used only with api.errors.format 'problemDetails' or 'negotiating'.";
        yield 'handler and format' => ['{handler: Tomaj\NetteApi\Error\DefaultErrorHandler, format: default}', $both];
        yield 'handler and typeBaseUri' => ['{handler: Tomaj\NetteApi\Error\DefaultErrorHandler, typeBaseUri: x}', $both];
        yield 'typeBaseUri alone' => ['{typeBaseUri: x}', $base];
        yield 'typeBaseUri with default' => ['{format: default, typeBaseUri: x}', $base];
    }

    #[DataProvider('invalidErrors')]
    public function testInvalidErrors(string $errors, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        ContainerFactory::compile("api:\n    errors: " . $errors);
    }

    public function testUnknownErrorFormatFailsSchema(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches("~The item 'api\\W+errors\\W+format' expects to be 'default'\\|'problemDetails'\\|'negotiating'\\|null, 'problem_details' given~");
        ContainerFactory::compile("api:\n    errors: {format: problem_details}");
    }

    public function testCorsMaskingAndPreflight(): void
    {
        $container = ContainerFactory::create(<<<NEON
            api:
                cors:
                    mode: auto
                    allowedOrigins: [https://app.example.com]
                    allowCredentials: true
                    preflight: true
                logging:
                    maskHeaders: true
            NEON);

        $presenter = $container->getService('api.presenter');
        $this->assertSame('auto', $this->property($presenter, 'corsHeader'));
        $allowlist = $this->property($presenter, 'corsAllowlist');
        $this->assertIsObject($allowlist);
        $this->assertSame(['https://app.example.com'], $this->property($allowlist, 'origins'));
        $this->assertTrue($this->property($allowlist, 'allowCredentials'));
        $this->assertSame(array_map(strtolower(...), ApiPresenter::DEFAULT_MASKED_LOG_HEADERS), $this->property($presenter, 'maskedLogHeaders'));
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
        $this->assertSame(['x-secret'], $this->property($presenter, 'maskedLogHeaders'));
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
                        - setCorsHeader(auto)
            api:
            NEON);
        $this->assertSame('auto', $this->property($container->getService('withMode'), 'corsHeader'));

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

    public function testCredentialsWithoutOriginsFail(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('api.cors.allowCredentials needs api.cors.allowedOrigins.');
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
                cors: {mode: auto}
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
        $this->assertSame('auto', $this->property($presenter, 'corsHeader'));
        $this->assertSame($container->getService('errorHandler'), $container->getByType(ErrorHandlerInterface::class));
        $this->assertSame($container->getService('configurator'), $container->getByType(ConfiguratorInterface::class));
        $this->assertSame($container->getService('ipDetector'), $container->getByType(IpDetectorInterface::class));
    }

    /**
     * @return \Iterator<string, array{string, string, string}>
     */
    public static function conflictingServices(): \Iterator
    {
        yield 'errors' => [DefaultErrorHandler::class, 'errors: {format: problemDetails}', "You set api.errors, but service 'mine' of type Tomaj\\NetteApi\\Error\\ErrorHandlerInterface is registered in the services section too. Remove one of them."];
        yield 'errors handler' => [DefaultErrorHandler::class, 'errors: {handler: Tomaj\NetteApi\Error\DefaultErrorHandler}', 'You set api.errors, but'];
        yield 'errors typeBaseUri' => [DefaultErrorHandler::class, 'errors: {format: negotiating, typeBaseUri: x}', 'You set api.errors, but'];
        yield 'output configurator' => [DebuggerConfigurator::class, 'outputConfigurator: env', "You set api.outputConfigurator, but service 'mine' of type Tomaj\\NetteApi\\Output\\Configurator\\ConfiguratorInterface"];
        yield 'ip detector' => [IpDetector::class, 'ipDetector: Tomaj\NetteApi\Misc\IpDetector', "You set api.ipDetector, but service 'mine' of type Tomaj\\NetteApi\\Misc\\IpDetectorInterface"];
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
        $this->expectExceptionMessage("Services 'one', 'two' are all of type Tomaj\\NetteApi\\ApiDecider. Keep only one, the api extension does not know which one to use.");
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
        $this->expectExceptionMessage("Service 'deciderFactory' must be a plain service (with factory: or create:), the api extension adds setup calls to it.");
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

    private function property(object $object, string $name): mixed
    {
        return new ReflectionProperty($object, $name)->getValue($object);
    }
}
