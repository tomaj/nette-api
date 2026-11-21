<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Handlers\TypedHandler;

/**
 * DTO metadata compiled with the DI container, or once per process when the extension is not used.
 * The container installs every handler serialized; a handler is unserialized when it is first used.
 */
final class DtoMetadataRegistry
{
    /** @var array<string, string> serialized DtoMetadata from the container, keyed by the handler */
    private static array $installed = [];

    /** @var array<string, DtoMetadata> */
    private static array $metadata = [];

    /**
     * @param array<class-string, string> $serialized serialize() of each handler's DtoMetadata
     */
    public static function install(array $serialized): void
    {
        self::$installed = $serialized;
        self::$metadata = [];
    }

    /**
     * @param class-string<TypedHandler> $handler
     */
    public static function forHandler(string $handler): DtoMetadata
    {
        if (isset(self::$metadata[$handler])) {
            return self::$metadata[$handler];
        }
        if (!isset(self::$installed[$handler])) {
            return self::$metadata[$handler] = DtoCompiler::compile($handler);
        }

        // the container is generated code, so its payload is trusted
        $metadata = unserialize(self::$installed[$handler]);
        if (!$metadata instanceof DtoMetadata) {
            throw new \LogicException(sprintf('Compiled DTO metadata of %s is invalid. Rebuild the DI container.', $handler));
        }

        return self::$metadata[$handler] = $metadata;
    }

    /**
     * @internal
     */
    public static function reset(): void
    {
        self::$installed = [];
        self::$metadata = [];
    }
}
