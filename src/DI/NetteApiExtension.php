<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\Application\IPresenterFactory;
use Nette\Caching\Storage as CacheStorage;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\Extensions\InjectExtension;
use Nette\DI\InvalidConfigurationException;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Predis\ClientInterface as PredisClientInterface;
use Psr\Clock\ClockInterface;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface;
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
use Tomaj\NetteApi\Logger\LogMaskMode;
use Tomaj\NetteApi\Logger\LogValueMasker;
use Tomaj\NetteApi\Logger\LogValueMaskerInterface;
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
use Tomaj\NetteApi\RateLimit\FixedWindowLimiter;
use Tomaj\NetteApi\RateLimit\Interval;
use Tomaj\NetteApi\RateLimit\Key\RateLimitKeys;
use Tomaj\NetteApi\RateLimit\LegacyRateLimitAdapter;
use Tomaj\NetteApi\RateLimit\RateLimitAlgorithm;
use Tomaj\NetteApi\RateLimit\AlgorithmLimiter;
use Tomaj\NetteApi\RateLimit\Algorithm\FixedWindow;
use Tomaj\NetteApi\RateLimit\Algorithm\RateLimitAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\Algorithm\RedisScriptAlgorithmInterface;
use Tomaj\NetteApi\RateLimit\RateLimiterChain;
use Tomaj\NetteApi\RateLimit\RateLimiterInterface;
use Tomaj\NetteApi\RateLimit\RateLimitInterface;
use Tomaj\NetteApi\RateLimit\SlidingWindowLimiter;
use Tomaj\NetteApi\RateLimit\Storage\InMemoryStorage;
use Tomaj\NetteApi\RateLimit\Storage\NetteCacheStorage;
use Tomaj\NetteApi\RateLimit\Storage\RateLimitStorageInterface;
use Tomaj\NetteApi\RateLimit\Storage\LazyStorage;
use Tomaj\NetteApi\RateLimit\Storage\RedisStorage;
use Nette\PhpGenerator\Literal;
use Tomaj\NetteApi\RateLimit\Redis\RedisClientInterface;
use Tomaj\NetteApi\RateLimit\TokenBucketLimiter;
use Tracy\ILogger;

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
 * @phpstan-type RateLimitDefinition array{algorithm: string, limit: int, interval: int|string, key: string|list<string>, name: string|null}
 * @phpstan-type RateLimitValue string|Statement|list<string>|RateLimitDefinition
 * @phpstan-type EndpointValue string|Statement|false|null
 * @phpstan-type Config object{
 *     outputConfigurator: ServiceValue|null,
 *     ipDetector: ServiceValue|null,
 *     logger: ServiceValue|null,
 *     errors: object{format: string|null, typeBaseUri: string|null, handler: ServiceValue|null},
 *     cors: object{mode: string|null, allowedOrigins: list<string>|null, originValidator: ServiceValue|null, allowCredentials: bool, preflight: bool|array<string, string|list<string>>},
 *     logging: object{maskHeaders: bool|list<string>|object{names: list<string>|null, add: list<string>, remove: list<string>, patterns: bool|list<string>|object{add: list<string>, remove: list<string>}}, maskQueryParams: bool|list<string>|object{names: list<string>|null, add: list<string>, remove: list<string>}, masking: ServiceValue, hashKey: string|Statement|null},
 *     authorizations: array<string, ServiceValue>,
 *     rateLimits: array<string, ServiceValue|RateLimitDefinition>,
 *     rateLimiting: object{storage: ServiceValue, redis: ServiceValue|null, prefix: string, clock: ServiceValue|null, logger: ServiceValue|null},
 *     defaults: object{authorization: ServiceValue|null, rateLimit: RateLimitValue|null},
 *     discovery: object{paths: list<string>, namespace: string|null},
 *     endpoints: list<object{method: string, version: string|int|float, package: string, action: string|null, handler: ServiceValue, authorization: EndpointValue, rateLimit: RateLimitValue|false|null}>
 * }
 */
final class NetteApiExtension extends CompilerExtension
{
    private const string PRESENTER_MAPPING = 'Tomaj\\NetteApi\\Presenters\\*Presenter';

    private const array ERROR_FORMATS = ['default', 'problemDetails', 'negotiating'];

    private const array RATE_LIMITERS = [
        'fixedWindow' => FixedWindowLimiter::class,
        'slidingWindow' => SlidingWindowLimiter::class,
        'tokenBucket' => TokenBucketLimiter::class,
    ];

    private const array RATE_LIMIT_STORAGES = ['cache', 'memory', 'redis'];

    private const array REDIS_CLIENTS = [\Redis::class, \RedisCluster::class, PredisClientInterface::class, RedisClientInterface::class];

    /** not under rateLimit., where the policies from api.rateLimits are */
    private const string RATE_LIMIT_STORAGE = 'rateLimiting.storage';

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

    /** @var list<\Closure(): void> checks of the rate limits that need the types of all services */
    private array $rateLimitChecks = [];

    /** @var array<string, string> policy names of api.rateLimits => where they are defined */
    private array $ratePolicies = [];

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
        $limit = static fn (): Schema => Expect::structure([
            'algorithm' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->default(RateLimitAlgorithm::SlidingWindow->value),
            'limit' => Expect::int()->required(),
            'interval' => Expect::anyOf(Expect::int(), Expect::string())->required(),
            'key' => Expect::anyOf(Expect::string(), Expect::listOf('string'))->default('ip'),
            'name' => Expect::string()->nullable()->default(null),
        ])->castTo('array');
        $rateLimit = static fn (): Schema => Expect::anyOf(Expect::string(), Expect::type(Statement::class), Expect::listOf('string')->min(1), $limit());

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
                'maskHeaders' => Expect::anyOf(Expect::bool(), Expect::listOf('string'), Expect::structure([
                    'names' => Expect::listOf('string')->nullable()->default(null),
                    'add' => Expect::listOf('string'),
                    'remove' => Expect::listOf('string'),
                    'patterns' => Expect::anyOf(Expect::bool(), Expect::listOf('string'), Expect::structure([
                        'add' => Expect::listOf('string'),
                        'remove' => Expect::listOf('string'),
                    ]))->default(true),
                ]))->default(true),
                'maskQueryParams' => Expect::anyOf(Expect::bool(), Expect::listOf('string'), Expect::structure([
                    'names' => Expect::listOf('string')->nullable()->default(null),
                    'add' => Expect::listOf('string'),
                    'remove' => Expect::listOf('string'),
                ]))->default(true),
                'masking' => $service()->default(LogMaskMode::Full->value),
                'hashKey' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable()->default(null),
            ]),
            'authorizations' => Expect::arrayOf($service(), 'string'),
            'rateLimits' => Expect::arrayOf(Expect::anyOf(Expect::string(), Expect::type(Statement::class), $limit()), 'string'),
            'rateLimiting' => Expect::structure([
                'storage' => $service()->default('cache'),
                'redis' => $service()->nullable()->default(null),
                'prefix' => Expect::string(RedisStorage::PREFIX),
                'clock' => $service()->nullable()->default(null),
                'logger' => $service()->nullable()->default(null),
            ]),
            'defaults' => Expect::structure([
                'authorization' => $service()->nullable()->default(null),
                'rateLimit' => $rateLimit()->nullable()->default(null),
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
                'rateLimit' => Expect::anyOf($rateLimit(), false)->nullable()->default(null),
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

        $policies = [];
        foreach (['authorizations' => $config->authorizations, 'rateLimits' => $config->rateLimits] as $section => $named) {
            $prefix = $section === 'authorizations' ? 'authorization' : 'rateLimit';
            $type = $section === 'authorizations' ? ApiAuthorizationInterface::class : RateLimiterInterface::class;
            foreach ($named as $name => $value) {
                if (preg_match('~^[A-Za-z0-9_]+$~D', $name) !== 1) {
                    throw new InvalidConfigurationException(sprintf(
                        "api.%s: the name '%s' is not valid, a name has only letters, digits and underscores. Use for example %s: {%s: App\\Api\\My%s}.",
                        $section,
                        $name,
                        $section,
                        $prefix === 'authorization' ? 'bearer' : 'strict',
                        ucfirst($prefix),
                    ));
                }
                $where = sprintf('api.%s.%s', $section, $name);
                $definition = $builder->addDefinition($this->prefix($prefix . '.' . $name))->setAutowired(false);
                if (is_array($value)) {
                    $policy = is_string($value['name'] ?? null) ? $value['name'] : $name;
                    if (isset($policies[$policy])) {
                        throw new InvalidConfigurationException(sprintf(
                            "%s and %s both use the policy name '%s', so they would share their counters. Give each one its own name.",
                            $policies[$policy],
                            $where,
                            $policy,
                        ));
                    }
                    $policies[$policy] = $where;
                    $this->ratePolicies[$policy] = $where;
                    $definition->setFactory($this->limiter($value, $policy, $where));
                } elseif ($type === RateLimiterInterface::class) {
                    // a RateLimitInterface of 3.x becomes LegacyRateLimitAdapter, so the service has one type
                    $definition->setType($type)->setFactory(new Statement([LegacyRateLimitAdapter::class, 'wrap'], [$this->service($value, [], $where, $type)]));
                } else {
                    $definition->setType($type)->setFactory($this->service($value, [], $where, $type));
                }
            }
        }

        // checked here too, so a typo fails even before any endpoint uses the default
        if ($config->defaults->authorization !== null) {
            $this->service($config->defaults->authorization, $this->namedServices('authorization'), 'api.defaults.authorization', ApiAuthorizationInterface::class, 'authorization');
        }
        if ($config->defaults->rateLimit !== null) {
            $this->rateLimit($config->defaults->rateLimit, 'api.defaults.rateLimit', 'default');
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
                $this->endpointRateLimit($endpoint, $config->defaults->rateLimit),
            ]);
        }

        foreach ($this->rateLimitChecks as $check) {
            $check();
        }
        $this->checkRateLimitStorage();
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

        $masking = $this->logMasking($config->logging->masking, $config->logging->hashKey);
        $logging = [
            'maskHeaders' => LoggingConfig::headers($config->logging->maskHeaders),
            'maskQueryParams' => LoggingConfig::queryParams($config->logging->maskQueryParams),
            'masking' => $masking === null ? [] : [['setLogMasking', [$masking]]],
        ];
        foreach ($logging as $key => $calls) {
            if ($calls !== []) {
                LoggingConfig::checkPresenterSetup($presenter->getSetup(), $key);
            }
            foreach ($calls as [$method, $arguments]) {
                $presenter->addSetup($method, $arguments);
            }
        }
    }

    private function logMasking(string|Statement $masking, string|Statement|null $hashKey): Statement|Reference|null
    {
        $mode = is_string($masking) ? LogMaskMode::tryFrom($masking) : null;
        if ($hashKey !== null && $mode !== LogMaskMode::Hash) {
            throw new InvalidConfigurationException(sprintf(
                'api.logging.hashKey is set, but api.logging.masking is %s and only hash uses the key. Use masking: hash with the key, or remove hashKey.',
                is_string($masking) ? "'" . $masking . "'" : 'a statement',
            ));
        }
        if ($hashKey === '') {
            throw new InvalidConfigurationException("api.logging.hashKey is empty, so it would not protect short secrets. Use a long random secret, for example hashKey: %env.API_LOG_HASH_KEY%, or remove hashKey for a plain SHA-256.");
        }
        if ($mode !== null) {
            if ($mode === LogMaskMode::Full) {
                return null;
            }
            return new Statement(LogValueMasker::class, [new Statement([LogMaskMode::class, 'from'], [$mode->value]), $hashKey]);
        }
        $modes = array_map(static fn (LogMaskMode $mode): string => $mode->value, LogMaskMode::cases());
        if (is_string($masking) && !str_starts_with($masking, '@') && !str_contains($masking, '\\')) {
            throw new InvalidConfigurationException(sprintf(
                "api.logging.masking '%s' is not a masking mode.%s Use %s, or a class implementing %s, @serviceName or a statement like App\\Api\\MyMasker(%%tempDir%%) for your own.",
                $masking,
                Hint::didYouMean($masking, $modes),
                Hint::quoted($modes),
                LogValueMaskerInterface::class,
            ));
        }
        return $this->service($masking, [], 'api.logging.masking', LogValueMaskerInterface::class);
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
            ApiAuthorizationInterface::class,
            $kind,
        );
    }

    /**
     * @param string|Statement|array<mixed>|null $default
     */
    private function endpointRateLimit(EndpointDefinition $endpoint, string|Statement|array|null $default): Statement|Reference|null
    {
        $value = $endpoint->rateLimit ?? $default;
        if ($value === false || $value === null) {
            return null;
        }

        // an inline limit has its own counters per endpoint, a named one is shared by all endpoints that use the name
        $policy = trim((string) preg_replace('~[^A-Za-z0-9_.-]+~', '.', $endpoint->method . '.' . 'v' . $endpoint->version . '.' . $endpoint->package . '.' . ($endpoint->action ?? '')), '.');
        $where = sprintf('the rateLimit of endpoint %s (%s)', $endpoint->describe(), $endpoint->source);
        $rateLimit = $this->rateLimit($value, $where, $policy);
        // only an own rate limit: a default one with identity may be shared by endpoints with and without the authorization
        if ($endpoint->rateLimit !== null) {
            $this->checkIdentityPolicy($value, $endpoint, $where);
        }
        return $rateLimit;
    }

    /**
     * An identity key on an endpoint whose authorization gives no identity would never limit anything
     *
     * @param string|Statement|array<mixed> $value
     */
    private function checkIdentityPolicy(string|Statement|array $value, EndpointDefinition $endpoint, string $where): void
    {
        $policy = $this->identityPolicy($value);
        if ($policy === null) {
            return;
        }
        $authorization = $endpoint->authorization === false
            ? NoAuthorization::class
            : $endpoint->authorization ?? $this->config()->defaults->authorization ?? NoAuthorization::class;
        if (is_string($authorization) && isset($this->config()->authorizations[$authorization])) {
            $authorization = $this->config()->authorizations[$authorization];
        }
        $class = $this->classOf($authorization);
        // an interface type of a service is not known, the service can still implement the identity
        if ($class === null || !class_exists($class) || is_a($class, AuthorizationIdentityInterface::class, true)) {
            return;
        }
        throw new InvalidConfigurationException(sprintf(
            '%s uses %s keyed by identity, but its authorization %s does not implement %s, so the limit would never apply. '
            . 'Use key: ip, or give the endpoint an authorization that implements it (for example BearerTokenAuthorization).',
            $where,
            $policy,
            $class,
            AuthorizationIdentityInterface::class,
        ));
    }

    /**
     * The built-in policy in a rate limit value that needs the identity, null when there is none or it is not known
     *
     * @param string|Statement|array<mixed> $value
     */
    private function identityPolicy(string|Statement|array $value): ?string
    {
        if (is_string($value)) {
            $named = $this->config()->rateLimits[$value] ?? null;
            return is_array($named) && self::needsIdentity($named) ? sprintf('api.rateLimits.%s', $value) : null;
        }
        if (!is_array($value)) {
            return null;
        }
        if (!array_is_list($value)) {
            return self::needsIdentity($value) ? 'an inline limit' : null;
        }
        foreach ($value as $item) {
            $policy = is_string($item) ? $this->identityPolicy($item) : null;
            if ($policy !== null) {
                return $policy;
            }
        }
        return null;
    }

    /**
     * @param array<mixed> $definition already checked by limiter()
     */
    private static function needsIdentity(array $definition): bool
    {
        /** @var string|array<mixed> $key */
        $key = $definition['key'] ?? 'ip';
        return RateLimitKeys::fromName($key)->needsIdentity();
    }

    /**
     * A rate limit value: a name from api.rateLimits, @service, a class, a statement, a list of them, or an inline limit
     *
     * @param string|Statement|array<mixed> $value
     */
    private function rateLimit(string|Statement|array $value, string $where, string $policy): Statement|Reference
    {
        if (!is_array($value)) {
            return $this->service($value, $this->namedServices('rateLimit'), $where, RateLimiterInterface::class, 'rateLimit');
        }
        if (!array_is_list($value)) {
            // an inline limit always counts per endpoint, also with an own name, which changes only the policy in the headers
            $name = $value['name'] ?? null;
            if (is_string($name) && isset($this->ratePolicies[$name])) {
                throw new InvalidConfigurationException(sprintf(
                    "%s uses the policy name '%s' of %s. Give the inline limit its own name, or use rateLimit: %s to share the counters.",
                    $where,
                    $name,
                    $this->ratePolicies[$name],
                    $name,
                ));
            }
            $key = (array) ($value['key'] ?? 'ip');
            return $this->limiter(['key' => in_array('endpoint', $key, true) ? $key : [...$key, 'endpoint']] + $value, is_string($name) ? $name : $policy, $where);
        }
        if ($value === []) {
            throw new InvalidConfigurationException(sprintf('The list of rate limits in %s is empty. Use names from api.rateLimits, for example rateLimit: [perIp, perUser], or false for no rate limit.', $where));
        }

        $limiters = [];
        $named = $this->namedServices('rateLimit');
        foreach ($value as $index => $item) {
            if (!is_string($item)) {
                throw new InvalidConfigurationException(sprintf('Item %d of the rate limit list in %s is %s, but it must be a name from api.rateLimits, @service or a class, for example rateLimit: [perIp, perUser] or #[RateLimit([\'perIp\', \'perUser\'])].', $index, $where, get_debug_type($item)));
            }
            $limiter = $this->service($item, $named, sprintf('%s[%d]', $where, $index), RateLimiterInterface::class, 'rateLimit');
            // a named one is adapted already, a RateLimitInterface of 3.x in the list cannot go to the chain as it is
            $limiters[] = isset($named[$item]) ? $limiter : new Statement([LegacyRateLimitAdapter::class, 'wrap'], [$limiter]);
        }
        return new Statement(RateLimiterChain::class, $limiters);
    }

    /**
     * A built-in limiter from {algorithm, limit, interval, key}, checked here so a typo fails the container compilation
     *
     * @param array<mixed> $definition
     */
    private function limiter(array $definition, string $policy, string $where): Statement
    {
        $algorithm = $definition['algorithm'] ?? RateLimitAlgorithm::SlidingWindow->value;
        $class = is_string($algorithm) ? self::RATE_LIMITERS[$algorithm] ?? null : null;
        $custom = $class === null ? $this->customAlgorithm($algorithm, $where) : null;
        [$limit, $interval, $key] = self::limitValues($definition, $where);
        try {
            $class === null
                ? new AlgorithmLimiter(new FixedWindow(), $policy, $limit, $interval, RateLimitKeys::fromName($key, $where), new InMemoryStorage())
                : new $class($policy, $limit, $interval, RateLimitKeys::fromName($key, $where), new InMemoryStorage());
        } catch (\InvalidArgumentException $e) {
            throw new InvalidConfigurationException($e->getMessage(), previous: $e);
        }

        $rateLimiting = $this->config()->rateLimiting;
        return new Statement($class ?? AlgorithmLimiter::class, [
            ...($custom === null ? [] : [$custom]),
            $policy,
            $limit,
            Interval::toSeconds($interval, $where),
            new Statement([RateLimitKeys::class, 'fromName'], [$key, $where]),
            $this->rateLimitStorage(),
            $rateLimiting->clock === null ? null : $this->service($rateLimiting->clock, [], 'api.rateLimiting.clock', ClockInterface::class),
            // not autowired, more ILogger services in an application must not fail the compilation
            $rateLimiting->logger === null ? null : $this->service($rateLimiting->logger, [], 'api.rateLimiting.logger', ILogger::class),
        ]);
    }

    /**
     * The config schema and #[RateLimit] already guarantee these types, the check only narrows them
     *
     * @infection-ignore-all
     * @param array<mixed> $definition
     * @return array{int, int|string, string|array<mixed>}
     */
    private static function limitValues(array $definition, string $where): array
    {
        $limit = $definition['limit'] ?? null;
        $interval = $definition['interval'] ?? null;
        $key = $definition['key'] ?? 'ip';
        if (!is_int($limit) || (!is_int($interval) && !is_string($interval)) || (!is_string($key) && !is_array($key))) {
            throw new InvalidConfigurationException(sprintf(
                'The rate limit in %s needs limit and interval, for example {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}.',
                $where,
            ));
        }
        return [$limit, $interval, $key];
    }

    /**
     * An own algorithm in rateLimits.*.algorithm: a class or @service implementing RateLimitAlgorithmInterface
     */
    private function customAlgorithm(mixed $algorithm, string $where): Statement|Reference
    {
        if ($algorithm instanceof Statement || (is_string($algorithm) && str_starts_with($algorithm, '@'))) {
            $service = $this->service($algorithm, [], $where . '.algorithm', RateLimitAlgorithmInterface::class);
            $this->rateLimitChecks[] = function () use ($algorithm, $where): void {
                $class = $this->classOf($algorithm);
                if ($class !== null) {
                    $this->checkRedisAlgorithm($class, $where);
                }
            };
            return $service;
        }
        if (!is_string($algorithm) || !class_exists($algorithm)) {
            throw new InvalidConfigurationException(sprintf(
                "The rate limit in %s has an unknown algorithm '%s'.%s Use one of %s, or a class implementing %s.",
                $where,
                is_scalar($algorithm) ? $algorithm : get_debug_type($algorithm),
                Hint::didYouMean(is_string($algorithm) ? $algorithm : '', array_keys(self::RATE_LIMITERS)),
                Hint::quoted(array_keys(self::RATE_LIMITERS)),
                RateLimitAlgorithmInterface::class,
            ));
        }
        if (!is_subclass_of($algorithm, RateLimitAlgorithmInterface::class)) {
            throw new InvalidConfigurationException(sprintf(
                'The rate limit algorithm %s in %s does not implement %s. Implement step(), and also %s to use it with Redis.',
                $algorithm,
                $where,
                RateLimitAlgorithmInterface::class,
                RedisScriptAlgorithmInterface::class,
            ));
        }
        $this->rateLimitChecks[] = fn () => $this->checkRedisAlgorithm($algorithm, $where);
        return new Statement($algorithm);
    }

    /**
     * Redis needs the decision as one Lua script, without it every request would fail open at runtime
     */
    private function checkRedisAlgorithm(string $algorithm, string $where): void
    {
        if ($this->usesRedis() && !is_a($algorithm, RedisScriptAlgorithmInterface::class, true)) {
            throw new InvalidConfigurationException(sprintf(
                'The rate limit algorithm %s in %s cannot run in Redis (api.rateLimiting.storage: %s), which needs the decision as one atomic Lua script. Implement %s in it, or use another storage.',
                ltrim($algorithm, '\\'),
                $where,
                self::describe($this->config()->rateLimiting->storage),
                RedisScriptAlgorithmInterface::class,
            ));
        }
    }

    /**
     * api.rateLimiting.storage is redis, or an own RedisStorage (@service, a class or a statement)
     */
    private function usesRedis(): bool
    {
        $storage = $this->config()->rateLimiting->storage;
        if ($storage === 'redis') {
            return true;
        }
        $class = $this->classOf($storage);
        return $class !== null && is_a($class, RedisStorage::class, true);
    }

    /**
     * The class of a class name, a statement like App\MyClass(arguments) or a @service with a known type, null when it is not known yet
     */
    private function classOf(mixed $value): ?string
    {
        if ($value instanceof Statement) {
            $value = $value->getEntity();
        }
        if (!is_string($value)) {
            return null;
        }
        if (str_starts_with($value, '@')) {
            $builder = $this->getContainerBuilder();
            $name = substr($value, 1);
            return $builder->hasDefinition($name) ? $builder->getDefinition($name)->getType() : null;
        }
        return class_exists($value) ? $value : null;
    }

    private static function describe(mixed $value): string
    {
        if ($value instanceof Statement) {
            $entity = $value->getEntity();
            return (is_string($entity) ? $entity : get_debug_type($entity)) . '(...)';
        }
        return is_string($value) ? $value : get_debug_type($value);
    }

    /**
     * The storage of the built-in limiters, created on the first use: api.rateLimiting.storage
     */
    private function rateLimitStorage(): Reference
    {
        $name = $this->prefix(self::RATE_LIMIT_STORAGE);
        $builder = $this->getContainerBuilder();
        if ($builder->hasDefinition($name)) {
            return new Reference($name);
        }

        $rateLimiting = $this->config()->rateLimiting;
        $storage = $rateLimiting->storage;
        $factory = match ($storage) {
            // the lock files go to %tempDir% of the application, not to a temp directory shared with other users
            'cache' => new Statement(NetteCacheStorage::class, is_string($builder->parameters['tempDir'] ?? null) ? ['lockDirectory' => $builder->parameters['tempDir'] . '/nette-api-rate-limit'] : []),
            'memory' => new Statement(InMemoryStorage::class),
            'redis' => new Statement(RedisStorage::class, [
                $rateLimiting->redis === null
                    ? throw new InvalidConfigurationException("api.rateLimiting.storage is redis, but api.rateLimiting.redis is not set. Add the Redis client service, for example rateLimiting: {storage: redis, redis: @redis} with services: {redis: Redis()} (ext-redis, call connect() in the setup) or Predis\Client('tcp://127.0.0.1:6379') (predis/predis).")
                    : $this->redisClient($rateLimiting->redis),
                $rateLimiting->prefix,
            ]),
            default => is_string($storage) && !str_starts_with($storage, '@') && !class_exists($storage)
                ? throw new InvalidConfigurationException(sprintf(
                    "api.rateLimiting.storage '%s' is not known.%s Use cache (Nette cache, the default, atomic only on one server), memory (one PHP process only, for tests), redis (atomic, needs rateLimiting.redis), @service or a class implementing %s.",
                    $storage,
                    Hint::didYouMean($storage, self::RATE_LIMIT_STORAGES),
                    RateLimitStorageInterface::class,
                ))
                : $this->service($storage, [], 'api.rateLimiting.storage', RateLimitStorageInterface::class),
        };
        // the real storage is created on the first request with a rate limit, a Redis outage then fails open instead of the API
        $builder->addDefinition($name . 'Factory')
            ->setType(RateLimitStorageInterface::class)
            ->setFactory($factory)
            ->setAutowired(false);
        $builder->addDefinition($name)
            ->setType(LazyStorage::class)
            ->setFactory(new Statement(LazyStorage::class, [new Literal('fn() => $this->getService(?)', [$name . 'Factory'])]))
            ->setAutowired(false);

        return new Reference($name);
    }

    private function redisClient(string|Statement $client): Statement|Reference
    {
        $class = $this->classOf($client);
        $expected = sprintf('Use @service with a Redis client, or a class: %s.', implode(', ', self::REDIS_CLIENTS));
        $this->rateLimitChecks[] = function () use ($client, $expected): void {
            $class = $this->classOf($client);
            if ($class !== null && !array_any(self::REDIS_CLIENTS, fn(string $type): bool => is_a($class, $type, true))) {
                throw new InvalidConfigurationException(sprintf('api.rateLimiting.redis %s is %s, which is not a Redis client. %s', self::describe($client), $class, $expected));
            }
        };
        if (is_string($client) && !str_starts_with($client, '@') && ($class === null || !class_exists($class) || new \ReflectionClass($class)->getName() !== ltrim($client, '\\'))) {
            // 'redis' without @ would be the class \Redis: a client without connect(), so every request would pass without a limit
            throw new InvalidConfigurationException(sprintf("api.rateLimiting.redis '%s' is not a @service nor a class, for a service write @%s. %s", $client, $client, $expected));
        }
        if (is_string($client) && str_starts_with($client, '@')) {
            return new Reference(substr($client, 1));
        }
        return $client instanceof Statement ? $client : new Statement($client);
    }

    /**
     * The cache storage needs a Nette\Caching\Storage service, nette/bootstrap registers one (cache.storage)
     */
    private function checkRateLimitStorage(): void
    {
        $builder = $this->getContainerBuilder();
        if ($this->config()->rateLimiting->storage !== 'cache' || !$builder->hasDefinition($this->prefix(self::RATE_LIMIT_STORAGE))) {
            return;
        }
        if ($builder->findByType(CacheStorage::class) === []) {
            throw new InvalidConfigurationException(sprintf(
                'The rate limits in api.rateLimits use api.rateLimiting.storage cache (the default), but no %s service exists. Register the cache extension (extensions: {cache: Nette\Bridges\CacheDI\CacheExtension(%%tempDir%%)}, nette/bootstrap does it for you), or choose another storage, for example rateLimiting: {storage: redis, redis: @redis}.',
                CacheStorage::class,
            ));
        }
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
            "Unknown %s '%s' in %s, it is not a name from api.%s nor an existing class.%s %s Or use @serviceName, a class implementing %s, or a statement like App\\MyClass(arguments). To add the name, write api: {%s: {%s: %s}}.",
            $kind,
            $value,
            $where,
            $section,
            Hint::didYouMean($value, array_keys($named)),
            $named === [] ? sprintf('No names are defined in api.%s yet.', $section) : sprintf('Names defined in api.%s: %s.', $section, Hint::quoted(array_keys($named))),
            $type,
            $section,
            $value,
            $kind === 'authorization' ? 'App\\Api\\MyAuthorization' : '{algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}',
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
        if ($type === RateLimiterInterface::class && is_a($class, RateLimitInterface::class, true)) {
            // deprecated, works through LegacyRateLimitAdapter
            return;
        }
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
