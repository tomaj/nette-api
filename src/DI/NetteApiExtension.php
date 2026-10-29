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
use Tomaj\NetteApi\Handlers\CorsPreflightHandler;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator;
use Tomaj\NetteApi\Output\Configurator\EnvConfigurator;
use Tomaj\NetteApi\Output\Configurator\QueryConfigurator;
use Tomaj\NetteApi\Presenters\ApiPresenter;
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
 *     cors: object{mode: string|null, allowedOrigins: list<string>|null, allowCredentials: bool, preflight: bool|array<string, string|list<string>>},
 *     logging: object{maskHeaders: bool|list<string>},
 *     authorizations: array<string, ServiceValue>,
 *     rateLimits: array<string, ServiceValue>,
 *     defaults: object{authorization: ServiceValue|null, rateLimit: ServiceValue|null},
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

        return Expect::structure([
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
            'endpoints' => Expect::listOf(Expect::structure([
                'method' => Expect::string()->required(),
                'version' => Expect::anyOf(Expect::string(), Expect::int(), Expect::float())->required(),
                'package' => Expect::string()->required(),
                'action' => Expect::string()->nullable()->default(null),
                'handler' => $service()->required(),
                'authorization' => $endpointService()->nullable()->default(null),
                'rateLimit' => $endpointService()->nullable()->default(null),
            ])),
        ]);
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
            foreach ($named as $name => $value) {
                $builder->addDefinition($this->prefix($prefix . '.' . $name))
                    ->setType($prefix === 'authorization' ? ApiAuthorizationInterface::class : RateLimitInterface::class)
                    ->setFactory($this->service($value, [], sprintf('api.%s.%s', $section, $name), $prefix === 'authorization' ? ApiAuthorizationInterface::class : RateLimitInterface::class))
                    ->setAutowired(false);
            }
        }

        foreach ($config->endpoints as $index => $endpoint) {
            if (is_float($endpoint->version)) {
                throw new InvalidConfigurationException(sprintf(
                    "Version %s in api.endpoints[%d] is a number with a decimal point, write it in quotes: version: '%s'.",
                    $endpoint->version,
                    $index,
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
        $config = $this->config();

        $this->useOwnOrExisting('outputConfigurator', ConfiguratorInterface::class, $config->outputConfigurator !== null ? 'api.outputConfigurator' : null);
        $this->useOwnOrExisting('errorHandler', ErrorHandlerInterface::class, $this->errorsConfigured($config->errors) ? 'api.errors' : null);
        $this->useOwnOrExisting('ipDetector', IpDetectorInterface::class, $config->ipDetector !== null ? 'api.ipDetector' : null);
        $presenter = $this->useOwnOrExisting('presenter', ApiPresenter::class, null);
        $decider = $this->useOwnOrExisting('decider', ApiDecider::class, null);

        $this->configurePresenter($presenter, $config);
        $this->addPresenterMapping();

        if ($config->cors->preflight !== false) {
            $decider->addSetup('enableGlobalPreflight', $config->cors->preflight === true
                ? []
                : [new Statement(CorsPreflightHandler::class, ['headers' => $config->cors->preflight])]);
        }

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
                throw new InvalidConfigurationException('Use either api.errors.handler, or api.errors.format with api.errors.typeBaseUri, not both.');
            }

            return $this->service($errors->handler, [], 'api.errors.handler', ErrorHandlerInterface::class);
        }

        if ($errors->typeBaseUri !== null && !in_array($errors->format, ['problemDetails', 'negotiating'], true)) {
            throw new InvalidConfigurationException("api.errors.typeBaseUri is used only with api.errors.format 'problemDetails' or 'negotiating'.");
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

    private static function hasSetup(ServiceDefinition $definition, string $method): bool
    {
        return array_any($definition->getSetup(), fn(Statement $setup): bool => self::isCall($setup, $method));
    }

    /**
     * @param Config $config
     */
    private function configurePresenter(ServiceDefinition $presenter, object $config): void
    {
        if ($config->cors->mode !== null) {
            $presenter->addSetup('setCorsHeader', [$config->cors->mode]);
        } elseif (!self::hasSetup($presenter, 'setCorsHeader')) {
            $presenter->addSetup('setCorsHeader', ['off']);
        }

        if ($config->cors->allowedOrigins !== null) {
            $presenter->addSetup('setCorsAllowedOrigins', [$config->cors->allowedOrigins, $config->cors->allowCredentials]);
        } elseif ($config->cors->allowCredentials) {
            throw new InvalidConfigurationException('api.cors.allowCredentials needs api.cors.allowedOrigins.');
        }

        if ($config->logging->maskHeaders === true) {
            $presenter->addSetup('setMaskedLogHeaders');
        } elseif (is_array($config->logging->maskHeaders)) {
            $presenter->addSetup('setMaskedLogHeaders', [$config->logging->maskHeaders]);
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
                "You set %s, but service '%s' of type %s is registered in the services section too. Remove one of them.",
                $configuredBy,
                $names[0],
                $type,
            ));
        }

        if (count($existing) > 1) {
            throw new InvalidConfigurationException(sprintf(
                "Services '%s' are all of type %s. Keep only one, the api extension does not know which one to use.",
                implode("', '", $names),
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
            throw new InvalidConfigurationException(sprintf("Service '%s' must be a plain service (with factory: or create:), the api extension adds setup calls to it.", $name));
        }

        return $definition;
    }

    private function handlerService(EndpointDefinition $endpoint): string
    {
        $builder = $this->getContainerBuilder();
        $handler = $endpoint->handler;

        if ($handler instanceof Statement) {
            $entity = $handler->getEntity();
            if (is_string($entity) && class_exists($entity)) {
                $this->checkType($entity, ApiHandlerInterface::class, sprintf('the handler of endpoint %s (%s)', $endpoint->describe(), $endpoint->source));
            }
            $name = $this->prefix('handler.' . ++$this->handlerCount);
            $builder->addDefinition($name)
                ->setFactory($handler)
                ->setAutowired(false);
            return '@@' . $name;
        }

        if (str_starts_with($handler, '@')) {
            if (!$builder->hasDefinition(substr($handler, 1))) {
                throw new InvalidConfigurationException(sprintf("Handler of endpoint %s (%s): service '%s' does not exist.", $endpoint->describe(), $endpoint->source, substr($handler, 1)));
            }

            return '@' . $handler;
        }

        $class = ltrim($handler, '\\');

        if (!class_exists($class) || !is_a($class, ApiHandlerInterface::class, true)) {
            throw new InvalidConfigurationException(sprintf(
                "Handler of endpoint %s (%s): '%s' is not a class implementing %s. Use a handler class, a @service or a statement like App\\Api\\MyHandler(%%tempDir%%).",
                $endpoint->describe(),
                $endpoint->source,
                $handler,
                ApiHandlerInterface::class,
            ));
        }

        $existing = [];
        foreach ($builder->findByType($class) as $existingName => $definition) {
            if ($definition->getType() === $class && $definition->getAutowired() !== false) {
                $existing[] = $existingName;
            }
        }
        if (count($existing) > 1) {
            throw new InvalidConfigurationException(sprintf(
                "Handler of endpoint %s (%s): services '%s' are all of class %s. Use '@serviceName' as the handler.",
                $endpoint->describe(),
                $endpoint->source,
                implode("', '", $existing),
                $class,
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

        $known = $kind === 'authorization' ? $this->config()->authorizations : $this->config()->rateLimits;
        $prefixed = [];
        foreach (array_keys($known) as $name) {
            $prefixed[$name] = $this->prefix($kind . '.' . $name);
        }

        return $this->service(
            $value,
            $prefixed,
            sprintf('the %s of endpoint %s (%s)', $kind, $endpoint->describe(), $endpoint->source),
            $kind === 'authorization' ? ApiAuthorizationInterface::class : RateLimitInterface::class,
        );
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
     */
    private function service(string|Statement $value, array $named, string $where, string $type): Statement|Reference
    {
        if ($value instanceof Statement) {
            $entity = $value->getEntity();
            if (is_string($entity) && class_exists($entity)) {
                $this->checkType($entity, $type, $where);
            }
            return $value;
        }

        if (str_starts_with($value, '@')) {
            return new Reference(substr($value, 1));
        }

        if (isset($named[$value])) {
            return new Reference($named[$value]);
        }

        if (class_exists($value)) {
            $this->checkType($value, $type, $where);
            return new Statement($value);
        }

        $hint = $named === [] ? '' : sprintf(" Defined names: '%s'.", implode("', '", array_keys($named)));
        throw new InvalidConfigurationException(sprintf(
            "Unknown value '%s' in %s. Use a name defined in the api section, @serviceName, a class name or a statement like App\\MyClass(arguments).%s",
            $value,
            $where,
            $hint,
        ));
    }

    /**
     * @param class-string $type
     */
    private function checkType(string $class, string $type, string $where): void
    {
        if (!is_a($class, $type, true)) {
            throw new InvalidConfigurationException(sprintf("Class '%s' in %s must implement %s.", ltrim($class, '\\'), $where, $type));
        }
    }
}
