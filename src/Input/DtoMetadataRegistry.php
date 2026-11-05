<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Handlers\TypedHandler;

/**
 * DTO metadata compiled with the DI container, or once per process when the extension is not used.
 */
final class DtoMetadataRegistry
{
    /** @var array<class-string, DtoMetadata> */
    private array $handlers = [];

    /** @var array<class-string, DtoMetadata> */
    private static array $runtime = [];

    private static ?self $installed = null;

    /**
     * @param array<class-string, array<string, mixed>> $exported
     */
    public static function install(array $exported): void
    {
        $registry = new self();
        foreach ($exported as $handler => $data) {
            $registry->handlers[$handler] = DtoMetadata::import($data);
        }
        self::$installed = $registry;
    }

    /**
     * @internal
     */
    public static function installed(): bool
    {
        return self::$installed !== null;
    }

    /**
     * @internal
     */
    public static function installs(string $handler): bool
    {
        return self::$installed !== null && isset(self::$installed->handlers[$handler]);
    }

    /**
     * @param class-string<TypedHandler> $handler
     */
    public static function forHandler(string $handler): DtoMetadata
    {
        if (self::$installed !== null && isset(self::$installed->handlers[$handler])) {
            return self::$installed->handlers[$handler];
        }
        if (isset(self::$runtime[$handler])) {
            return self::$runtime[$handler];
        }

        return self::$runtime[$handler] = DtoCompiler::compile($handler);
    }

    /**
     * @internal
     */
    public static function reset(): void
    {
        self::$installed = null;
        self::$runtime = [];
    }
}
