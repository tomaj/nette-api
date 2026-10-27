<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;

/**
 * Creates operation parameters (query, cookie, header, path) of handler
 */
interface ParameterBuilderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(ApiHandlerInterface $handler): array;
}
