<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Extensions\InjectExtension;
use Nette\DI\InvalidConfigurationException;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Error\DefaultErrorHandler;
use Tomaj\NetteApi\Error\ErrorHandlerInterface;
use Tomaj\NetteApi\Error\NegotiatingErrorHandler;
use Tomaj\NetteApi\Error\ProblemDetailsErrorHandler;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\CorsOriginAllowlist;
use Tomaj\NetteApi\Misc\CorsOriginValidatorInterface;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Output\Configurator\EnvConfigurator;
use Tomaj\NetteApi\Output\Configurator\QueryConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Presenters\CorsMode;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;

/**
 * Registers the API services and endpoints from the "api:" neon section:
 *
 *     extensions:
 *         api: Tomaj\NetteApi\DI\NetteApiExtension
 *
 * Everything is resolved while the DI container is compiled, the compiled container only calls addApi().
 * A service you register yourself (ApiDecider, ApiPresenter, error handler, output configurator,
 * IP detector) is used instead of the one from the extension.
 *
 * @phpstan-type ServiceValue string|Statement
 * @phpstan-type EndpointValue string|Statement|false|null
 * @phpstan-type Config object{
 *     outputConfigurator: ServiceValue|null,
 *     ipDetector: ServiceValue|null,
 *     logger: ServiceValue|null,
 *     errors: object{format: string|null, typeBaseUri: string|null, handler: ServiceValue|null},
 *     cors: object{mode: string|null, allowedOrigins: list<string>|null, originValidator: ServiceValue|null, allowCredentials: bool, preflight: bool|array<string, string|list<string>>},
 *     logging: object{maskHeaders: bool|list<string>},
 *     authorizations: array<string, ServiceValue>,
 *     rateLimits: array<string, ServiceValue>,
 *     defaults: object{authorization: ServiceValue|null, rateLimit: ServiceValue|null},
 *     discovery: object{paths: list<string>, namespace: string|null},
 *     endpoints: list<object{method: string, version: string|int|float, package: string, action: string|null, handler: ServiceValue, authorization: EndpointValue, rateLimit: EndpointValue}>
 * }
 */
final class NetteApiExtension extends CompilerExtension
{
    private const string PRESENTER_MAPPING = 'Tomaj\\NetteApi\\Presenters\\*Presenter';

    private const array ERROR_FORMATS = ['default', 'problemDetails', 'negotiating'];

    private const array OUTPUT_CONFIGURATORS = [
        'env' => EnvConfigurator::class,
        'debugger' => DebuggerConfigurator::class,
        'query' => QueryConfigurator::class,
    ];

    private readonly EndpointCollector $endpoints;

    private int $handlerCount = 0;

    /** all services are registered, so a missing "@name" is an error */
    private bool $compiling = false;

    /** @var list<array{string, class-string, string}> "@name" references checked once all services are registered */
    private array $references = [];

    /** @var list<class-string> handler classes with endpoint attributes found in api.discovery.paths */
    private array $discovered = [];

    /** @var array<string, string> own service name => service it returns ("@name" in neon) */
    private array $aliases = [];

    public function __construct()
    {
        $this->endpoints = new EndpointCollector();
    }

    #[\Override]
    public function getConfigSchema(): Schema
    {
        $service = static fn (): Schema => Expect::anyOf(Expect::string(), Expect::type(Statement::class));
        $endpointService = static fn (): Schema => Expect::anyOf(Expect::string(), Expect::type(Statement::class), false);

        return new ConfigSchema(Expect::structure([
            'outputConfigurator' => $service()->nullable()->default(null),
            'ipDetector' => $service()->nullable()->default(null),
            'logger' => $service()->nullable()->default(null),
            'errors' => Expect::structure([
                'format' => Expect::anyOf(...self::ERROR_FORMATS)->nullable()->default(null),
                'typeBaseUri' => Expect::string()->nullable()->default(null),
                'handler' => $service()->nullable()->default(null),
            ]),
            'cors' => Expect::structure([
                'mode' => Expect::string()->nullable()->default(null),
                'allowedOrigins' => Expect::listOf('string')->nullable()->default(null),
                'originValidator' => $service()->nullable()->default(null),
                'allowCredentials' => Expect::bool(false),
                'preflight' => Expect::anyOf(Expect::bool(), Expect::arrayOf(Expect::anyOf(Expect::string(), Expect::listOf('string')), 'string'))->default(false),
            ]),
            'logging' => Expect::structure([
                'maskHeaders' => Expect::anyOf(Expect::bool(), Expect::listOf('string'))->default(false),
            ]),
            'authorizations' => Expect::arrayOf($service(), 'string'),
            'rateLimits' => Expect::arrayOf($service(), 'string'),
            'defaults' => Expect::structure([
                'authorization' => $service()->nullable()->default(null),
                'rateLimit' => $service()->nullable()->default(null),
            ]),
            'discovery' => Expect::structure([
                'paths' => Expect::listOf('string'),
                'namespace' => Expect::string()->nullable()->default(null),
            ]),
            'endpoints' => Expect::listOf(Expect::structure([
                'method' => Expect::string()->required(),
                'version' => Expect::anyOf(Expect::string(), Expect::int(), Expect::float())->required(),
                'package' => Expect::string()->required(),
                'action' => Expect::string()->nullable()->default(null),
                'handler' => $service()->required(),
                'authorization' => $endpointService()->nullable()->default(null),
                'rateLimit' => $endpointService()->nullable()->default(null),
            ])),
        ]));
    }

    #[\Override]
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $config = $this->config();

        $builder->addDefinition($this->prefix('decider'))
            ->setFactory(ApiDecider::class);

        $builder->addDefinition($this->prefix('presenter'))
            ->setFactory(ApiPresenter::class)
            ->addTag(InjectExtension::TagInject)
            ->setAutowired(false);

        $this->addOwnService('outputConfigurator', ConfiguratorInterface::class, $this->outputConfigurator($config->outputConfigurator));
        $this->addOwnService('errorHandler', ErrorHandlerInterface::class, $this->errorHandler($config->errors));
        $this->addOwnService('ipDetector', IpDetectorInterface::class, $config->ipDetector === null
            ? new Statement(IpDetector::class)
            : $this->service($config->ipDetector, [], 'api.ipDetector', IpDetectorInterface::class));

        if ($config->logger !== null) {
            $this->addOwnService('logger', ApiLoggerInterface::class, $this->service($config->logger, [], 'api.logger', ApiLoggerInterface::class));
        }

        foreach (['authorizations' => $config->authorizations, 'rateLimits' => $config->rateLimits] as $section => $named) {
            $prefix = $section === 'authorizations' ? 'authorization' : 'rateLimit';
            $type = $section === 'authorizations' ? ApiAuthorizationInterface::class : RateLimitInterface::class;
            foreach ($named as $name => $value) {
                if (preg_match('~^\w+$~', $name) !== 1) {
                    throw new InvalidConfigurationException(sprintf(
                        "api.%s: the name '%s' is not valid, a name has only letters, digits and underscores. Use for example %s: {%s: App\\Api\\My%s}.",
                        $section,
                        $name,
                        $section,
                        $prefix === 'authorization' ? 'bearer' : 'strict',
                        ucfirst($prefix),
                    ));
                }
                $builder->addDefinition($this->prefix($prefix . '.' . $name))
                    ->setType($type)
                    ->setFactory($this->service($value, [], sprintf('api.%s.%s', $section, $name), $type))
                    ->setAutowired(false);
            }
        }

        // checked here too, so a typo fails even before any endpoint uses the default
        foreach (['authorization' => $config->defaults->authorization, 'rateLimit' => $config->defaults->rateLimit] as $kind => $default) {
            if ($default !== null) {
                $this->service($default, $this->namedServices($kind), 'api.defaults.' . $kind, $kind === 'authorization' ? ApiAuthorizationInterface::class : RateLimitInterface::class, $kind);
            }
        }

        foreach ($config->endpoints as $index => $endpoint) {
            if (is_float($endpoint->version)) {
                throw new InvalidConfigurationException(sprintf(
                    "api.endpoints[%d].version: %s is read by neon as a decimal number, so for example 1.10 would become 1.1. Write the version in quotes: version: '%s'.",
                    $index,
                    $endpoint->version,
                    $endpoint->version,
                ));
            }
            $this->addEndpoint(new EndpointDefinition(
                $endpoint->method,
                $endpoint->version,
                $endpoint->package,
                $endpoint->action,
                $endpoint->handler,
                $endpoint->authorization,
                $endpoint->rateLimit,
                sprintf('api.endpoints[%d]', $index),
            ));
        }

        if ($config->discovery->namespace !== null && $config->discovery->paths === []) {
            throw new InvalidConfigurationException(sprintf(
                "api.discovery.namespace '%s' is set, but api.discovery.paths is empty, and the namespace limits only the classes found in the paths. Add the directories, for example discovery: {paths: [%%appDir%%/Api], namespace: %s}, or remove the namespace.",
                $config->discovery->namespace,
                $config->discovery->namespace,
            ));
        }

        if ($config->discovery->paths !== []) {
            $this->discovered = AttributeDiscovery::scan($config->discovery->paths, $config->discovery->namespace);
            $this->compiler->addDependencies(AttributeDiscovery::dependencies($config->discovery->paths));
        }
    }

    /**
     * "@name" becomes $container->getService('name'), because Nette turns a plain reference into
     * a non-autowired alias and ApiPresenter needs these services autowired.
     *
     * @param class-string $type
     */
    private function addOwnService(string $name, string $type, Statement|Reference $factory): void
    {
        if ($factory instanceof Reference) {
            $this->aliases[$this->prefix($name)] = $factory->getValue();
            $factory = new Statement([new Reference(ContainerBuilder::ThisContainer), 'getService'], [$factory->getValue()]);
        }

        $this->getContainerBuilder()->addDefinition($this->prefix($name))
            ->setType($type)
            ->setFactory($factory);
    }

    /**
     * Adds an endpoint from another source than the api.endpoints list (e.g. discovered handlers).
     * The same method + version + package + action defined twice fails the container compilation.
     */
    public function addEndpoint(EndpointDefinition $endpoint): void
    {
        $this->endpoints->add($endpoint);
    }

    #[\Override]
    public function beforeCompile(): void
    {
        $this->compiling = true;
        $config = $this->config();
        foreach ($this->references as [$name, $type, $where]) {
            $this->checkReference($name, $type, $where);
        }

        $this->useOwnOrExisting('outputConfigurator', ConfiguratorInterface::class, $config->outputConfigurator !== null ? 'api.outputConfigurator' : null);
        $this->useOwnOrExisting('errorHandler', ErrorHandlerInterface::class, $this->errorsConfigured($config->errors) ? 'api.errors' : null);
        $this->useOwnOrExisting('ipDetector', IpDetectorInterface::class, $config->ipDetector !== null ? 'api.ipDetector' : null);
        if ($config->logger !== null) {
            // the presenter gets the logger by type, so a second logger would fail only on the first request
            $this->useOwnOrExisting('logger', ApiLoggerInterface::class, 'api.logger');
        }
        $presenter = $this->useOwnOrExisting('presenter', ApiPresenter::class, null);
        $decider = $this->useOwnOrExisting('decider', ApiDecider::class, null);

        $this->configurePresenter($presenter, $config);
        $this->addPresenterMapping();

        if ($config->cors->preflight !== false) {
            $decider->addSetup('enableGlobalPreflight', $config->cors->preflight === true
                ? []
                : [new Statement(CorsPreflightHandler::class, ['headers' => $config->cors->preflight])]);
        }

        $this->discoverEndpoints();

        foreach ($this->endpoints->all() as $endpoint) {
            $decider->addSetup('addApi', [
                new Statement(EndpointIdentifier::class, [$endpoint->method, $endpoint->version, $endpoint->package, $endpoint->action]),
                $this->handlerService($endpoint),
                $this->endpointService($endpoint->authorization, $config->defaults->authorization, 'authorization', $endpoint)
                    ?? new Statement(NoAuthorization::class),
                $this->endpointService($endpoint->rateLimit, $config->defaults->rateLimit, 'rateLimit', $endpoint),
            ]);
        }
    }

    /**
     * Endpoints from attributes of registered handler services and of classes found in api.discovery.paths.
     * An autowired registered service is used as it is, a class from the paths gets its own service
     * unless an autowired service of the class exists.
     */
    private function discoverEndpoints(): void
    {
        $handlers = [];
        $ignored = [];
        foreach ($this->getContainerBuilder()->findByType(ApiHandlerInterface::class) as $name => $definition) {
            /** @var class-string<ApiHandlerInterface> $type */
            $type = $definition->getType();
            // the same rule as handler: Class, a non-autowired service is used only through '@name'
            if ($definition->getAutowired() === false) {
                $ignored[$name] = $type;
                continue;
            }
            // Nette's class hash ignores class attributes, the file mtime makes an edited attribute rebuild the container
            $file = new \ReflectionClass($type)->getFileName();
            assert($file !== false, 'a handler service is a user class');
            $this->compiler->addDependencies([$file]);
            if (!AttributeDiscovery::hasRoutes($type)) {
                continue;
            }
            if (isset($handlers[$type])) {
                throw new InvalidConfigurationException(sprintf(
                    "Services '%s' and '%s' are both of class %s, which has endpoint attributes, so its endpoints would be registered twice. Remove one of the services, or set autowired: false on one of them (a service that is not autowired is not used for attribute endpoints).",
                    substr($handlers[$type], 1),
                    $name,
                    $type,
                ));
            }
            $handlers[$type] = '@' . $name;
        }

        foreach ($this->discovered as $class) {
            // an autowired service of the class is found by handlerService() and reused
            $handlers[$class] = $class;
        }

        $this->checkIgnoredServices($ignored, $handlers);

        ksort($handlers);
        foreach ($handlers as $class => $handler) {
            foreach (AttributeDiscovery::endpoints($class, $handler) as $endpoint) {
                $this->endpoints->add($endpoint);
            }
        }
    }

    /**
     * A non-autowired service with endpoint attributes that is neither discovered nor used as a handler
     * in api.endpoints: its attributes would silently do nothing.
     *
     * @param array<string, class-string> $ignored service name => class
     * @param array<class-string, string> $handlers
     */
    private function checkIgnoredServices(array $ignored, array $handlers): void
    {
        $used = [];
        foreach ($this->endpoints->all() as $endpoint) {
            if (is_string($endpoint->handler)) {
                // '@@name' is a quoted '@name', checkReference() reports it with a better hint
                $used[] = ltrim($endpoint->handler, '@');
            }
        }

        foreach ($ignored as $name => $class) {
            if (isset($handlers[$class]) || in_array($name, $used, true) || !AttributeDiscovery::hasRoutes($class)) {
                continue;
            }
            throw new InvalidConfigurationException(sprintf(
                "Service '%s' (class %s) has endpoint attributes, but it is registered with autowired: false, so the api extension ignores them. Remove autowired: false, or add the directory of the class to api.discovery.paths, or use the service in api.endpoints as handler: @%s (without quotes, in neon a quoted '@name' is a plain string).",
                $name,
                $class,
                $name,
            ));
        }
    }

    /**
     * @return Config
     */
    private function config(): object
    {
        /** @var Config $config */
        $config = $this->getConfig();
        return $config;
    }

    private function outputConfigurator(string|Statement|null $value): Statement|Reference
    {
        if ($value === null) {
            return new Statement(EnvConfigurator::class);
        }

        if (is_string($value) && isset(self::OUTPUT_CONFIGURATORS[$value])) {
            return new Statement(self::OUTPUT_CONFIGURATORS[$value]);
        }

        return $this->service($value, [], 'api.outputConfigurator', ConfiguratorInterface::class);
    }

    /**
     * @param object{format: string|null, typeBaseUri: string|null, handler: string|Statement|null} $errors
     */
    private function errorHandler(object $errors): Statement|Reference
    {
        if ($errors->handler !== null) {
            if ($errors->format !== null || $errors->typeBaseUri !== null) {
                throw new InvalidConfigurationException(sprintf(
                    'api.errors.handler is set together with %s, but format and typeBaseUri configure the built-in error handler, which your handler replaces. Remove %s, or remove handler and use only format (and typeBaseUri), for example errors: {format: problemDetails}.',
                    $errors->format !== null ? 'api.errors.format' : 'api.errors.typeBaseUri',
                    $errors->format !== null ? 'format' : 'typeBaseUri',
                ));
            }

            return $this->service($errors->handler, [], 'api.errors.handler', ErrorHandlerInterface::class);
        }

        if ($errors->typeBaseUri !== null && !in_array($errors->format, ['problemDetails', 'negotiating'], true)) {
            throw new InvalidConfigurationException(sprintf(
                "api.errors.typeBaseUri is set, but api.errors.format is %s and typeBaseUri is used only by the 'problemDetails' and 'negotiating' formats. Add format: problemDetails (or negotiating), or remove typeBaseUri.",
                $errors->format === null ? 'not set' : "'" . $errors->format . "'",
            ));
        }

        if ($errors->typeBaseUri !== null && $errors->typeBaseUri !== '' && preg_match('~^[a-z][a-z0-9+.-]*:\S*$~i', $errors->typeBaseUri) !== 1) {
            throw new InvalidConfigurationException(sprintf(
                "api.errors.typeBaseUri '%s' is not an absolute URI, the type of a problem is a URI. Use for example typeBaseUri: https://example.com/errors/.",
                $errors->typeBaseUri,
            ));
        }

        $problemDetails = new Statement(ProblemDetailsErrorHandler::class, ['typeBaseUri' => $errors->typeBaseUri]);

        return match ($errors->format) {
            'problemDetails' => $problemDetails,
            'negotiating' => new Statement(NegotiatingErrorHandler::class, [
                'fallback' => new Statement(DefaultErrorHandler::class),
                'problemDetails' => $problemDetails,
            ]),
            default => new Statement(DefaultErrorHandler::class),
        };
    }

    /**
     * @param object{format: string|null, typeBaseUri: string|null, handler: string|Statement|null} $errors
     */
    private function errorsConfigured(object $errors): bool
    {
        return $errors->format !== null || $errors->handler !== null || $errors->typeBaseUri !== null;
    }

    /**
     * Maps the Api module to ApiPresenter unless application: mapping already has an Api entry
     */
    private function addPresenterMapping(): void
    {
        $factory = $this->getContainerBuilder()->getByType(IPresenterFactory::class);
        if ($factory === null) {
            return;
        }

        $definition = $this->getContainerBuilder()->getDefinition($factory);
        if (!$definition instanceof ServiceDefinition) {
            return;
        }

        foreach ($definition->getSetup() as $setup) {
            if (self::isCall($setup, 'setMapping') && is_array($setup->arguments[0] ?? null) && array_key_exists('Api', $setup->arguments[0])) {
                return;
            }
        }

        $definition->addSetup('setMapping', [['Api' => self::PRESENTER_MAPPING]]);
    }

    private static function isCall(Statement $setup, string $method): bool
    {
        $entity = $setup->getEntity();
        return is_array($entity) && in_array($method, $entity, true);
    }

    /**
     * @param Config $config
     */
    private function configurePresenter(ServiceDefinition $presenter, object $config): void
    {
        $this->checkCors($config->cors);
        $this->checkHeaders($config);

        if ($config->cors->mode !== null) {
            $presenter->addSetup('setCorsHeader', [$config->cors->mode]);
        }

        if ($config->cors->allowedOrigins !== null) {
            $presenter->addSetup('setCorsAllowedOrigins', [$config->cors->allowedOrigins, $config->cors->allowCredentials]);
        } elseif ($config->cors->originValidator !== null) {
            $presenter->addSetup('setCorsOriginValidator', [
                $this->service($config->cors->originValidator, [], 'api.cors.originValidator', CorsOriginValidatorInterface::class),
                $config->cors->allowCredentials,
            ]);
        } elseif ($config->cors->allowCredentials) {
            throw new InvalidConfigurationException('api.cors.allowCredentials: true needs api.cors.allowedOrigins or api.cors.originValidator, credentials are allowed only for origins you allow. Add the origins, for example cors: {allowedOrigins: [https://app.example.com], allowCredentials: true}, or a validator, for example cors: {originValidator: App\Api\CustomerOrigins, allowCredentials: true}.');
        }

        if ($config->logging->maskHeaders === true) {
            $presenter->addSetup('setMaskedLogHeaders');
        } elseif (is_array($config->logging->maskHeaders)) {
            $presenter->addSetup('setMaskedLogHeaders', [$config->logging->maskHeaders]);
        }
    }

    /**
     * @param Config $config
     */
    private function checkHeaders(object $config): void
    {
        $preflight = $config->cors->preflight;
        if ($preflight === []) {
            throw new InvalidConfigurationException("api.cors.preflight is an empty map, so the preflight response would send no CORS headers. Use preflight: true for the default headers, or list them, for example preflight: {Access-Control-Allow-Headers: [Content-Type, Authorization]}.");
        }
        foreach (is_array($preflight) ? array_keys($preflight) : [] as $header) {
            self::checkHeaderName($header, sprintf("api.cors.preflight key '%s'", $header), "for example preflight: {Access-Control-Max-Age: '600'}");
        }

        $masked = $config->logging->maskHeaders;
        if ($masked === []) {
            throw new InvalidConfigurationException('api.logging.maskHeaders is an empty list, so no header would be masked, the same as false. Use maskHeaders: true for the default sensitive headers, list them, for example maskHeaders: [Authorization, X-Secret], or remove the option.');
        }
        foreach (is_array($masked) ? $masked : [] as $index => $header) {
            self::checkHeaderName($header, sprintf("api.logging.maskHeaders[%d] '%s'", $index, $header), 'for example maskHeaders: [Authorization, X-Secret]');
        }
    }

    private static function checkHeaderName(string $header, string $where, string $example): void
    {
        if (preg_match("~^[!#$%&'*+.^_`|\~0-9A-Za-z-]+$~", $header) !== 1) {
            throw new InvalidConfigurationException(sprintf(
                '%s is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, %s.',
                $where,
                $example,
            ));
        }
    }

    /**
     * @param object{mode: string|null, allowedOrigins: list<string>|null, originValidator: string|Statement|null} $cors
     */
    private function checkCors(object $cors): void
    {
        if ($cors->mode !== null && strtolower($cors->mode) === 'auto') {
            throw new InvalidConfigurationException(ApiPresenter::AUTO_RENAMED . 'Safe replacement: list the origins that call the API, for example cors: {allowedOrigins: [https://app.example.com], allowCredentials: true}; set allowCredentials only when the browser sends cookies or uses withCredentials. To keep the old behaviour anyway, use cors: {mode: reflect-unsafe}. See UPGRADE-4.0.md.');
        }
        $modes = array_map(static fn (CorsMode $mode): string => $mode->value, CorsMode::cases());
        if ($cors->mode !== null && !in_array($cors->mode, $modes, true) && !CorsOriginAllowlist::isOrigin($cors->mode)) {
            throw new InvalidConfigurationException(sprintf(
                "api.cors.mode '%s' is not valid.%s Use %s, or one origin like https://app.example.com (for more origins use api.cors.allowedOrigins).",
                $cors->mode,
                $cors->mode === '' ? ' It is empty.' : Hint::didYouMean($cors->mode, $modes),
                Hint::quoted($modes),
            ));
        }

        if ($cors->originValidator !== null) {
            foreach (['mode' => $cors->mode, 'allowedOrigins' => $cors->allowedOrigins] as $key => $value) {
                if ($value !== null) {
                    throw new InvalidConfigurationException(sprintf(
                        'api.cors.originValidator and api.cors.%s are both set, but only one of them can decide which origins are allowed. %s',
                        $key,
                        $key === 'mode'
                            ? 'Remove mode, the validator replaces it.'
                            : 'Remove allowedOrigins and allow the fixed origins in the validator too, for example return in_array($origin, $fixedOrigins, true) || ...;',
                    ));
                }
            }
        }

        if ($cors->allowedOrigins === null) {
            return;
        }

        if ($cors->mode !== null) {
            throw new InvalidConfigurationException(sprintf(
                "api.cors.mode '%s' and api.cors.allowedOrigins are both set, but allowedOrigins replaces mode, so mode does nothing. Remove mode.",
                $cors->mode,
            ));
        }

        if ($cors->allowedOrigins === []) {
            throw new InvalidConfigurationException("api.cors.allowedOrigins is an empty list, so no origin could call the API. Add the origins, for example allowedOrigins: [https://app.example.com], or remove the option.");
        }

        foreach ($cors->allowedOrigins as $index => $origin) {
            if (!CorsOriginAllowlist::isOrigin($origin)) {
                throw new InvalidConfigurationException(sprintf(
                    "api.cors.allowedOrigins[%d] '%s' is not an origin, it would never match the Origin header a browser sends. Write the scheme and host (and port) without a path or trailing slash, for example https://app.example.com or 'http://localhost:3000'.",
                    $index,
                    $origin,
                ));
            }
        }
    }

    /**
     * Keeps the extension's service, or removes it when the application already registers its own service
     * of the same type (then the extension configures that one).
     *
     * @param class-string $type
     */
    private function useOwnOrExisting(string $name, string $type, ?string $configuredBy): ServiceDefinition
    {
        $builder = $this->getContainerBuilder();
        $own = $this->prefix($name);
        $existing = $builder->findByType($type);
        unset($existing[$own], $existing[$this->aliases[$own] ?? '']);

        if ($existing === []) {
            return $this->serviceDefinition($own);
        }

        $names = array_keys($existing);
        if ($configuredBy !== null) {
            throw new InvalidConfigurationException(sprintf(
                "%s is set, but service '%s' of type %s is registered in the services section too, and the api extension does not know which one to use. Remove %s, or remove the service '%s'.",
                $configuredBy,
                $names[0],
                $type,
                $configuredBy,
                $names[0],
            ));
        }

        if (count($existing) > 1) {
            throw new InvalidConfigurationException(sprintf(
                "Services %s are all of type %s, and the api extension does not know which one to use. Keep only one of them (autowired: false does not help, the extension looks for every service of the type).",
                Hint::quoted($names),
                $type,
            ));
        }

        $builder->removeDefinition($own);
        return $this->serviceDefinition($names[0]);
    }

    private function serviceDefinition(string $name): ServiceDefinition
    {
        $definition = $this->getContainerBuilder()->getDefinition($name);
        if (!$definition instanceof ServiceDefinition) {
            throw new InvalidConfigurationException(sprintf(
                "Service '%s' must be a plain service (with factory: or create:), because the api extension adds setup calls to it. Define it for example as %s: %s.",
                $name,
                $name,
                $definition->getType() ?? 'App\\MyClass',
            ));
        }

        return $definition;
    }

    private function handlerService(EndpointDefinition $endpoint): string
    {
        $builder = $this->getContainerBuilder();
        $handler = $endpoint->handler;
        $where = sprintf('the handler of endpoint %s (%s)', $endpoint->describe(), $endpoint->source);

        if ($handler instanceof Statement) {
            $this->checkStatement($handler, ApiHandlerInterface::class, $where);
            $name = $this->prefix('handler.' . ++$this->handlerCount);
            $builder->addDefinition($name)
                ->setFactory($handler)
                ->setAutowired(false);
            return '@@' . $name;
        }

        if (str_starts_with($handler, '@')) {
            $this->checkReference(substr($handler, 1), ApiHandlerInterface::class, $where);
            return '@' . $handler;
        }

        $class = ltrim($handler, '\\');

        if (!class_exists($class)) {
            throw new InvalidConfigurationException(sprintf(
                "Class '%s' in %s does not exist. Check the spelling and the autoload section of composer.json. Use a handler class name, @serviceName, or a statement like App\\Api\\MyHandler(%%tempDir%%).",
                $class,
                $where,
            ));
        }
        $this->checkType($class, ApiHandlerInterface::class, $where);

        $existing = [];
        foreach ($builder->findByType($class) as $existingName => $definition) {
            if ($definition->getType() === $class && $definition->getAutowired() !== false) {
                $existing[] = $existingName;
            }
        }
        if (count($existing) > 1) {
            throw new InvalidConfigurationException(sprintf(
                "Class '%s' in %s is ambiguous, services %s are all autowired instances of it. Use the service name instead of the class, for example handler: @%s.",
                $class,
                $where,
                Hint::quoted($existing),
                $existing[0],
            ));
        }

        if ($existing === []) {
            $name = $this->prefix('handler.' . ++$this->handlerCount);
            $builder->addDefinition($name)->setFactory($class);
        } else {
            $name = $existing[0];
        }

        return '@@' . $name;
    }

    private function endpointService(string|Statement|false|null $value, string|Statement|null $default, string $kind, EndpointDefinition $endpoint): Statement|Reference|null
    {
        if ($value === false) {
            return null;
        }

        $value ??= $default;
        if ($value === null) {
            return null;
        }

        return $this->service(
            $value,
            $this->namedServices($kind),
            sprintf('the %s of endpoint %s (%s)', $kind, $endpoint->describe(), $endpoint->source),
            $kind === 'authorization' ? ApiAuthorizationInterface::class : RateLimitInterface::class,
            $kind,
        );
    }

    /**
     * Names from api.authorizations or api.rateLimits and their service names
     *
     * @return array<string, string>
     */
    private function namedServices(string $kind): array
    {
        $known = $kind === 'authorization' ? $this->config()->authorizations : $this->config()->rateLimits;
        $prefixed = [];
        foreach (array_keys($known) as $name) {
            $prefixed[$name] = $this->prefix($kind . '.' . $name);
        }

        return $prefixed;
    }

    /**
     * Turns a neon value into a service: "@name" is a reference, a name from api.authorizations / api.rateLimits
     * is that service, a class name or a statement creates a new instance.
     *
     * A class name (alone or in a statement) is checked against $type already here,
     * so a wrong class fails the container compilation instead of the first request.
     *
     * @param array<string, string> $named
     * @param class-string $type
     * @param string|null $kind 'authorization' or 'rateLimit' when a name from the api section can be used
     */
    private function service(string|Statement $value, array $named, string $where, string $type, ?string $kind = null): Statement|Reference
    {
        if ($value instanceof Statement) {
            $this->checkStatement($value, $type, $where);
            return $value;
        }

        if (str_starts_with($value, '@')) {
            $this->checkReference(substr($value, 1), $type, $where);
            return new Reference(substr($value, 1));
        }

        if (isset($named[$value])) {
            return new Reference($named[$value]);
        }

        if (class_exists($value)) {
            $this->checkType($value, $type, $where);
            return new Statement($value);
        }

        if ($kind === null) {
            throw new InvalidConfigurationException(sprintf(
                "Class '%s' in %s does not exist. Check the spelling and the autoload section of composer.json. Use a class implementing %s, @serviceName, or a statement like App\\MyClass(arguments).",
                $value,
                $where,
                $type,
            ));
        }

        $section = $kind === 'authorization' ? 'authorizations' : 'rateLimits';
        throw new InvalidConfigurationException(sprintf(
            "Unknown %s '%s' in %s, it is not a name from api.%s nor an existing class.%s %s Or use @serviceName, a class implementing %s, or a statement like App\\MyClass(arguments). To add the name, write api: {%s: {%s: App\\Api\\My%s}}.",
            $kind,
            $value,
            $where,
            $section,
            Hint::didYouMean($value, array_keys($named)),
            $named === [] ? sprintf('No names are defined in api.%s yet.', $section) : sprintf('Names defined in api.%s: %s.', $section, Hint::quoted(array_keys($named))),
            $type,
            $section,
            $value,
            ucfirst($kind),
        ));
    }

    /**
     * The class of a statement like App\MyClass(arguments) must exist and have the expected type.
     * Other statements (a factory method like App\Factory::create(), a reference like @service()) are left to Nette.
     *
     * @param class-string $type
     */
    private function checkStatement(Statement $statement, string $type, string $where): void
    {
        $entity = $statement->getEntity();
        if (!is_string($entity) || str_contains($entity, '::')) {
            return;
        }
        if (!class_exists($entity)) {
            throw new InvalidConfigurationException(sprintf(
                "Class '%s' in the statement in %s does not exist. Check the spelling and the autoload section of composer.json, the class must implement %s.",
                $entity,
                $where,
                $type,
            ));
        }
        $this->checkType($entity, $type, $where);
    }

    /**
     * A "@name" reference must point to an existing service of the expected type.
     * A service whose type is not known yet is left to Nette.
     *
     * @param class-string $type
     */
    private function checkReference(string $name, string $type, string $where): void
    {
        $builder = $this->getContainerBuilder();
        if (str_starts_with($name, '@')) {
            throw new InvalidConfigurationException(sprintf(
                "'%s' in %s is quoted, and in neon a quoted '@name' is a plain string, not a service reference. Write it without quotes: %s.",
                $name,
                $where,
                $name,
            ));
        }
        if (!$builder->hasDefinition($name)) {
            // another extension can still add the service, beforeCompile() checks it again
            if (!$this->compiling) {
                $this->references[] = [$name, $type, $where];
                return;
            }
            throw new InvalidConfigurationException(sprintf(
                "Service '%s' used as @%s in %s does not exist.%s Register it in the services section, or use a class name instead of @%s.",
                $name,
                $name,
                $where,
                Hint::didYouMean($name, array_keys($builder->getDefinitions())),
                $name,
            ));
        }

        $class = $builder->getDefinition($name)->getType();
        if ($class !== null) {
            $this->checkType($class, $type, sprintf("service '%s' (%s)", $name, $where));
        }
    }

    /**
     * @param class-string $type
     */
    private function checkType(string $class, string $type, string $where): void
    {
        if (!is_a($class, $type, true)) {
            throw new InvalidConfigurationException(sprintf(
                "Class '%s' in %s must implement %s, but it does not.%s",
                ltrim($class, '\\'),
                $where,
                $type,
                $type === ApiHandlerInterface::class
                    ? sprintf(' Extend %s (or implement the interface), or use another class.', BaseHandler::class)
                    : ($type === CorsOriginValidatorInterface::class && method_exists($class, '__invoke')
                        ? ' The class has __invoke(), but the extension needs the interface: add implements CorsOriginValidatorInterface with isAllowedOrigin(string $origin): bool, or pass the callable to ApiPresenter::setCorsOriginValidator() in PHP.'
                        : ' Implement the interface in the class, or use another class.'),
            ));
        }
    }
}
