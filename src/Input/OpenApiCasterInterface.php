<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Optional OpenAPI description of the wire value a caster accepts.
 * It never changes runtime validation. Without it, OpenAPI uses a string schema.
 */
interface OpenApiCasterInterface
{
    /**
     * @return array{type?: string, format?: string, example?: mixed}
     */
    public function openApiSchema(): array;
}
