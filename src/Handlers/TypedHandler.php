<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * A handler whose input is one readonly DTO. handle(array) is final; implement handleDto() instead.
 * The DTO class is the type of the handleDto() parameter.
 *
 * @method ResponseInterface handleDto(object $input) declared by the subclass with its own DTO type, checked when the container is compiled
 */
abstract class TypedHandler extends BaseHandler
{
    /**
     * @return array<ParamInterface>
     */
    #[\Override]
    final public function params(): array
    {
        return DtoMetadataRegistry::forHandler(static::class)->inputParams();
    }

    /**
     * @param array<string, mixed> $params
     */
    #[\Override]
    final public function handle(array $params): ResponseInterface
    {
        $metadata = DtoMetadataRegistry::forHandler(static::class);
        $input = $metadata->instantiate($params);
        // DtoCompiler checks at compile time that handleDto() exists, is public and takes exactly this DTO
        return $this->handleDto($input);
    }
}
