<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;

/**
 * Creates operation request body of handler
 */
interface RequestBodyBuilderInterface
{
    /**
     * @return array<string, mixed>|null null if handler has no body params
     */
    public function build(ApiHandlerInterface $handler, SchemaRegistry $schemaRegistry): ?array;
}
