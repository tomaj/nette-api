<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use InvalidArgumentException;
use Tomaj\NetteApi\Misc\OpenApiTransform;
use Tomaj\NetteApi\Misc\TypeHelper;

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
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>|null null if schema can't be encoded to JSON (e.g. invalid UTF-8 or INF in example)
     * @throws InvalidArgumentException if definition with the same name but different content already exists
     */
    public function transform(array $schema): ?array
    {
        OpenApiTransform::transformTypes($schema);

        if (isset($schema['definitions'])) {
            foreach (TypeHelper::toArray($schema['definitions']) as $name => $definition) {
                $this->add((string) $name, $this->transform(TypeHelper::toArray($definition)));
            }

            unset($schema['definitions']);
        }

        $transformed = json_decode(str_replace('#/definitions/', '#/components/schemas/', json_encode($schema, JSON_UNESCAPED_SLASHES) ?: ''), true);

        return is_array($transformed) ? $transformed : null;
    }

    /**
     * @param array<array-key, mixed>|null $definition
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
