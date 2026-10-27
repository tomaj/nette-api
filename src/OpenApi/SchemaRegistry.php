<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use InvalidArgumentException;
use Tomaj\NetteApi\Misc\OpenApiTransform;

/**
 * Transforms JSON schemas to OpenAPI schemas and collects their definitions as reusable components
 */
final class SchemaRegistry
{
    /** @var array<string|int, mixed> numeric definition names are int keys */
    private array $definitions = [];

    /**
     * Transform JSON schema to OpenAPI schema, definitions are moved to components and references are updated
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null null if schema can't be encoded to JSON (e.g. invalid UTF-8 or INF in example)
     * @throws InvalidArgumentException if definition with the same name but different content already exists
     */
    public function transform(array $schema): ?array
    {
        OpenApiTransform::transformTypes($schema);

        if (isset($schema['definitions'])) {
            foreach ($schema['definitions'] as $name => $definition) {
                $this->add((string) $name, $this->transform($definition));
            }

            unset($schema['definitions']);
        }

        return json_decode(str_replace('#/definitions/', '#/components/schemas/', json_encode($schema, JSON_UNESCAPED_SLASHES) ?: ''), true);
    }

    /**
     * @param array<string, mixed>|null $definition
     * @throws InvalidArgumentException if definition with the same name but different content already exists
     */
    public function add(string $name, ?array $definition): void
    {
        if (isset($this->definitions[$name]) && $this->definitions[$name] !== $definition) {
            throw new InvalidArgumentException('Definition with name ' . $name . ' already exists. Rename it.');
        }

        $this->definitions[$name] = $definition;
    }

    /**
     * @return array<string|int, mixed> numeric definition names are int keys
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }
}
