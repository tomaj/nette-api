<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;

/**
 * Creates operation responses
 */
interface ResponseBuilderInterface
{
    /**
     * Schemas referenced by default error responses, they are added to components/schemas
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildErrorSchemas(): array;

    /**
     * @param bool $hasInput true if handler has params (adds 400 Bad request response)
     * @return array<int, array<string, mixed>>
     */
    public function build(ApiHandlerInterface $handler, ApiAuthorizationInterface $authorization, bool $hasInput, SchemaRegistry $schemaRegistry): array;
}
