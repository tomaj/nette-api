<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\DI\InvalidConfigurationException;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Finder;
use ReflectionAttribute;
use ReflectionClass;
use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\RateLimit;
use Tomaj\NetteApi\Attribute\Route;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Handlers\BaseHandler;

/**
 * Reads the endpoint attributes (#[Get], #[Post], ..., #[Authorization], #[RateLimit]) of handler classes.
 *
 * @internal used by NetteApiExtension at container compile time
 */
final class AttributeDiscovery
{
    /**
     * Classes with an endpoint attribute in the given directories.
     *
     * @param list<string> $paths
     * @return list<class-string>
     */
    public static function scan(array $paths, ?string $namespace): array
    {
        foreach ($paths as $index => $path) {
            if (is_file($path)) {
                throw new InvalidConfigurationException(sprintf(
                    "api.discovery.paths[%d]: '%s' is a file, the paths are directories. Use the directory of the file, for example paths: [%%appDir%%/Api].",
                    $index,
                    $path,
                ));
            }
            if (!file_exists($path)) {
                throw new InvalidConfigurationException(sprintf(
                    "api.discovery.paths[%d]: directory '%s' does not exist. Write an absolute path, for example paths: [%%appDir%%/Api].",
                    $index,
                    $path,
                ));
            }
        }

        $loader = new RobotLoader();
        $loader->addDirectory(...$paths);
        $loader->rebuild();

        // App\Api, \App\Api\ and App\Api\ all mean classes in App\Api; null or '' means all classes
        $prefix = ltrim(trim((string) $namespace, '\\') . '\\', '\\');
        $indexed = $loader->getIndexedClasses();
        self::checkNamespace($prefix, $indexed, $paths);

        $classes = [];
        foreach ($indexed as $class => $file) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            try {
                $exists = class_exists($class) || interface_exists($class) || trait_exists($class);
            } catch (\Error $e) {
                throw new InvalidConfigurationException(sprintf(
                    'Class %s (%s) found by api.discovery cannot be loaded (%s). Fix the class, or keep it out of the discovery with api.discovery.paths or api.discovery.namespace.',
                    $class,
                    $file,
                    $e->getMessage(),
                ), previous: $e);
            }
            if (!$exists) {
                throw new InvalidConfigurationException(sprintf(
                    "Class %s (%s) found by api.discovery cannot be autoloaded. Add its namespace to the autoload section of composer.json (for example \"autoload\": {\"psr-4\": {\"App\\\\\": \"app/\"}}) and run composer dump-autoload, or keep the file out of api.discovery.paths.",
                    $class,
                    $file,
                ));
            }
            self::checkMisplacedAttributes($class);
            if (self::hasRoutes($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * A namespace that matches no class is a typo, otherwise the namespace option would not be set.
     *
     * @param array<string, string> $indexed
     * @param list<string> $paths
     */
    private static function checkNamespace(string $prefix, array $indexed, array $paths): void
    {
        if ($indexed === [] || array_any(array_keys($indexed), static fn (string $class): bool => str_starts_with($class, $prefix))) {
            return;
        }

        $namespaces = [];
        foreach (array_keys($indexed) as $class) {
            $namespaces[] = implode('\\', array_slice(explode('\\', $class), 0, -1));
        }
        $namespaces = array_unique($namespaces);
        $namespace = rtrim($prefix, '\\');

        throw new InvalidConfigurationException(sprintf(
            "api.discovery.namespace '%s' matches no class in api.discovery.paths (%s).%s Namespaces found there: %s. Fix the namespace, or remove the option to use all classes in the paths.",
            $namespace,
            Hint::quoted($paths),
            Hint::didYouMean($namespace, $namespaces),
            Hint::quoted($namespaces),
        ));
    }

    /**
     * Attributes the discovery would silently ignore: a route attribute on a method,
     * #[Authorization] or #[RateLimit] on a class without a route attribute.
     *
     * @param class-string $class
     */
    private static function checkMisplacedAttributes(string $class): void
    {
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getMethods() as $method) {
            foreach ([Route::class, Authorization::class, RateLimit::class] as $type) {
                foreach ($method->getAttributes($type, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    throw new InvalidConfigurationException(sprintf(
                        'Attribute #[%s] on method %s::%s() is ignored, endpoint attributes work only on the handler class. Move it above "class %s".',
                        self::shortName($attribute->getName()),
                        $class,
                        $method->getName(),
                        $reflection->getShortName(),
                    ));
                }
            }
        }

        if (self::hasRoutes($class)) {
            return;
        }
        foreach ([Authorization::class, RateLimit::class] as $type) {
            if ($reflection->getAttributes($type) !== []) {
                throw new InvalidConfigurationException(sprintf(
                    "Class %s has #[%s] but no route attribute, so it defines no endpoint and #[%s] is ignored. Add a route attribute, for example #[Get(1, 'users')], or remove #[%s].",
                    $class,
                    self::shortName($type),
                    self::shortName($type),
                    self::shortName($type),
                ));
            }
        }
    }

    /**
     * @param class-string $class
     */
    private static function shortName(string $class): string
    {
        return new ReflectionClass($class)->getShortName();
    }

    /**
     * Directories and PHP files under the paths. Nette compares their mtimes in debug mode, so a new file
     * in any subdirectory or an edited attribute rebuilds the container; the class hash Nette keeps
     * for handler classes does not include class attributes.
     *
     * @param list<string> $paths
     * @return list<string>
     */
    public static function dependencies(array $paths): array
    {
        $dependencies = $paths;
        foreach (Finder::findFiles(new RobotLoader()->acceptFiles)->directories()->from(...$paths) as $file) {
            $dependencies[] = $file->getPathname();
        }

        return $dependencies;
    }

    /**
     * @param class-string $class
     */
    public static function hasRoutes(string $class): bool
    {
        return new ReflectionClass($class)->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    /**
     * One endpoint per route attribute of the class.
     *
     * @param class-string $class
     * @param string $handler handler as written in neon: the class name or "@service"
     * @return list<EndpointDefinition>
     */
    public static function endpoints(string $class, string $handler): array
    {
        $reflection = new ReflectionClass($class);
        if (!$reflection->implementsInterface(ApiHandlerInterface::class)) {
            throw new InvalidConfigurationException(sprintf(
                'Class %s has an endpoint attribute, but it does not implement %s. Extend %s (or implement the interface), or remove the attribute.',
                $class,
                ApiHandlerInterface::class,
                BaseHandler::class,
            ));
        }
        if (!$reflection->isInstantiable()) {
            throw new InvalidConfigurationException(sprintf(
                'Class %s has an endpoint attribute, but it is abstract, so no handler can be created from it. Attributes are not inherited, put the attribute on each concrete handler class instead.',
                $class,
            ));
        }

        try {
            $authorization = ($reflection->getAttributes(Authorization::class)[0] ?? null)?->newInstance()->authorization;
            $rateLimit = ($reflection->getAttributes(RateLimit::class)[0] ?? null)?->newInstance()->rateLimit;

            $endpoints = [];
            foreach ($reflection->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $route = $attribute->newInstance();
                $endpoints[] = new EndpointDefinition(
                    $route->method,
                    $route->version,
                    $route->package,
                    $route->action,
                    $handler,
                    $authorization,
                    $rateLimit,
                    sprintf('#[%s] on %s', self::shortName($attribute->getName()), $class),
                );
            }
        } catch (\Error $e) {
            throw new InvalidConfigurationException(sprintf(
                "Invalid endpoint attribute on %s (%s). Check the attribute arguments, for example #[Get(1, 'users')] or #[Get(version: 1, package: 'users', action: 'detail')]; #[Authorization] and #[RateLimit] can be used only once per class.",
                $class,
                $e->getMessage(),
            ), previous: $e);
        }

        return $endpoints;
    }
}
