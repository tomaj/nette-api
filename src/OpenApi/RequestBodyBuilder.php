<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Params\RawInputParam;

/**
 * Creates operation request body from JSON, raw, POST, PUT and file params of handler
 */
class RequestBodyBuilder implements RequestBodyBuilderInterface
{
    /**
     * @return array<string, mixed>|null null if handler has no body params
     */
    #[\Override]
    public function build(ApiHandlerInterface $handler, SchemaRegistry $schemaRegistry): ?array
    {
        $requestBody = [
            'properties' => [],
            'required' => [],
        ];
        $filesInBody = false;
        $requestBodyExample = [];
        $result = [];
        $typedProperties = [];
        $typedRequired = [];
        $typedTypes = [];
        $typedFiles = [];
        foreach ($handler->params() as $param) {
            // a body param declared in params() without a schema is documented like a POST param: a string (or a list of them)
            $isBody = $param->getType() === InputParam::TYPE_BODY;
            if ($param instanceof InputParam && ($isBody || ($param->getType() === InputParam::TYPE_FILE && $param->getOpenApiSchema() !== null))) {
                $schema = $param->getOpenApiSchema() ?? $this->formProperty($param);
                if ($param->getDescription() !== '' && !isset($schema['description'])) {
                    $schema['description'] = $param->getDescription();
                }
                $typedProperties[$param->getKey()] = $schema;
                if ($param->getType() === InputParam::TYPE_FILE) {
                    $typedFiles[] = $param->getKey();
                }
                if ($param->isRequired()) {
                    $typedRequired[] = $param->getKey();
                }
                if ($param->getContentTypes() !== []) {
                    $typedTypes = $param->getContentTypes();
                }
                continue;
            }
            if ($param instanceof JsonInputParam) {
                $schema = TypeHelper::toArray(json_decode($param->getSchema(), true));
                if (!empty($examples = $param->getExamples())) {
                    if (count($examples) === 1) {
                        $schema['example'] = is_string($param->getExample()) ? json_decode($param->getExample(), true) : $param->getExample();
                    } else {
                        $schemaExamples = TypeHelper::toArray($schema['examples'] ?? null);
                        foreach ($examples as $exampleKey => $example) {
                            $schemaExamples[$exampleKey] = is_string($example) ? json_decode($example, true) : $example;
                        }

                        $schema['examples'] = $schemaExamples;
                    }
                }

                $result['description'] = $param->getDescription();
                $result['required'] = $param->isRequired();
                $result['content']['application/json'] = [
                    'schema' => $schemaRegistry->transform($schema),
                ];
            }

            if ($param instanceof RawInputParam) {
                $schema = [
                    'type' => 'string',
                ];
                if (!empty($examples = $param->getExamples())) {
                    if (count($examples) === 1) {
                        $schema['example'] = $param->getExample();
                    } else {
                        $schema['examples'] = $examples;
                    }
                }
                $result['description'] ??= $param->getDescription();
                $result['required'] ??= $param->isRequired();
                $result['content']['text/plain'] = [
                    'schema' => $schema,
                ];
            }

            if ($param->getType() === InputParam::TYPE_POST || $param->getType() === InputParam::TYPE_PUT) {
                $property = $this->formProperty($param);

                $requestBody['properties'][$param->getKey() . ($param->isMulti() ? '[]' : '')] = $property;
                if ($param->isRequired()) {
                    $requestBody['required'][] = $param->getKey() . ($param->isMulti() ? '[]' : '');
                }

                if ($param->getExample() || $param->getDefault()) {
                    $requestBodyExample[$param->getKey()] = $param->getExample() ?: $param->getDefault();
                }
            } elseif ($param->getType() === InputParam::TYPE_FILE) {
                $filesInBody = true;
                $property = [
                    'type' => $param->isMulti() ? 'array' : 'string',
                    'description' => $param->getDescription(),
                ];
                if ($param->isMulti()) {
                    $property['items'] = ['type' => 'string', 'format' => 'binary'];
                } else {
                    $property['format'] = 'binary';
                }

                $requestBody['properties'][$param->getKey() . ($param->isMulti() ? '[]' : '')] = $property;
                if ($param->isRequired()) {
                    $requestBody['required'][] = $param->getKey() . ($param->isMulti() ? '[]' : '');
                }
            }
        }

        if (!empty($requestBody['properties'])) {
            $requestBodySchema = [
                'type' => 'object',
                'properties' => $requestBody['properties'],
            ];

            if ($requestBody['required'] !== []) {
                $requestBodySchema['required'] = $requestBody['required'];
            }

            if ($requestBodyExample) {
                $requestBodySchema['example'] = $requestBodyExample;
            }

            $contentType = $filesInBody ? 'multipart/form-data' : 'application/x-www-form-urlencoded';

            $result['required'] = true;
            $result['content'][$contentType] = [
                'schema' => $requestBodySchema,
            ];
        }

        if ($typedProperties !== []) {
            $result['required'] = ($result['required'] ?? false) === true || $typedRequired !== [];
            // a required file cannot be sent as JSON, so such a request could only be answered with 400: only multipart is advertised
            if (array_intersect($typedRequired, $typedFiles) !== []) {
                $advertised = [MediaTypes::MULTIPART];
            } elseif ($typedTypes !== []) {
                $advertised = $typedTypes;
            } else {
                $advertised = [MediaTypes::JSON, $typedFiles === [] && !$filesInBody ? MediaTypes::FORM : MediaTypes::MULTIPART];
            }
            // OpenAPI 3.0 cannot describe a list of objects (or deeper nesting) in a form body, so such a body is documented
            // as JSON only, unless no other media type is left; files are sent only as multipart, so a body with files keeps it
            if (!$this->formDescribable($typedProperties)) {
                $describable = array_diff($advertised, $typedFiles === [] ? [MediaTypes::FORM, MediaTypes::MULTIPART] : [MediaTypes::FORM]);
                $advertised = $describable !== [] ? $describable : $advertised;
            }
            foreach ($advertised as $contentType) {
                // files can only be sent as multipart/form-data, so the other media types do not list them
                $properties = $contentType === MediaTypes::MULTIPART ? $typedProperties : array_diff_key($typedProperties, array_flip($typedFiles));
                if ($contentType !== MediaTypes::FORM && $contentType !== MediaTypes::MULTIPART) {
                    $result['content'][$contentType] = ['schema' => $this->objectSchema($properties, $typedRequired)];
                    continue;
                }
                $mediaType = $this->formMediaType($properties, $typedRequired, $contentType === MediaTypes::MULTIPART);
                // POST, PUT and file params without a schema already described this form: one body carries them all
                $legacy = TypeHelper::toArray($result['content'][$contentType]['schema'] ?? null);
                if ($legacy !== []) {
                    $mediaType['schema'] = $this->mergeObjects($legacy, $mediaType['schema']);
                }
                $result['content'][$contentType] = $mediaType;
            }
        }

        return $result ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formProperty(ParamInterface $param): array
    {
        $property = [
            'type' => $param->isMulti() ? 'array' : 'string',
        ];
        if ($param->isMulti()) {
            $property['items'] = ['type' => 'string'];
        }

        $descriptionParts = [];
        if ($param->getDescription()) {
            $descriptionParts[] = $param->getDescription();
        }

        $availableValues = $param->getAvailableValues();
        if ($availableValues) {
            $property['enum'] = array_keys($availableValues);
            if (array_keys($availableValues) !== array_values($availableValues)) {
                foreach ($availableValues as $availableKey => $availableValue) {
                    $descriptionParts[] = ' * `' . $availableKey . '` - ' . TypeHelper::toString($availableValue);
                }
            }
        }

        if ($descriptionParts !== []) {
            $property['description'] = implode("\n", $descriptionParts);
        }

        return $property;
    }

    /**
     * @param array<string, array<array-key, mixed>> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function objectSchema(array $properties, array $required): array
    {
        $schema = ['type' => 'object'];
        if ($properties !== []) {
            $schema['properties'] = $properties;
        }
        // every required property is listed: a required file means only multipart is advertised, and multipart lists files
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param array<array-key, mixed> $first
     * @param array<array-key, mixed> $second
     * @return array<array-key, mixed>
     */
    private function mergeObjects(array $first, array $second): array
    {
        $merged = $first;
        $merged['properties'] = TypeHelper::toArray($first['properties'] ?? null) + TypeHelper::toArray($second['properties'] ?? null);
        $required = array_values(array_unique([...TypeHelper::toStringList($first['required'] ?? null), ...TypeHelper::toStringList($second['required'] ?? null)]));
        if ($required !== []) {
            $merged['required'] = $required;
        }

        return $merged;
    }

    /**
     * The form wire shape PHP parses: a list is sent as `tags[]=a&tags[]=b`, so it is named `tags[]` (as POST params are),
     * an object as `address[street]=...`, which is the deepObject style. Null cannot be sent in a form, so nothing is nullable.
     * OpenAPI applies encoding styles only to urlencoded forms and sends an object part of multipart as JSON, so in multipart
     * the fields of an object are listed one by one under their bracket names.
     *
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     * @return array{schema: array<string, mixed>, encoding?: array<string, array{style: string, explode: bool}>}
     */
    private function formMediaType(array $properties, array $required, bool $multipart): array
    {
        $formProperties = [];
        $formRequired = [];
        $encoding = [];
        foreach ($properties as $name => $schema) {
            $schema = self::withoutNull($schema);
            $type = $schema['type'] ?? null;
            if (!$this->formDescribable([$schema])) {
                // still listed, so that no field of the body is missing from the document, with the wire shape in words
                $schema['description'] = ltrim(TypeHelper::toString($schema['description'] ?? '') . sprintf("\n\nSent as form fields in bracket notation (`%s[...]`), which OpenAPI 3.0 cannot describe.", $name));
                $formProperties[$name] = $schema;
                if (in_array($name, $required, true)) {
                    $formRequired[] = $name;
                }
                continue;
            }
            if ($type === 'object' && $multipart) {
                $childRequired = in_array($name, $required, true) ? TypeHelper::toStringList($schema['required'] ?? null) : [];
                foreach (TypeHelper::toArray($schema['properties'] ?? null) as $child => $childSchema) {
                    $childSchema = TypeHelper::toArray($childSchema);
                    $childName = sprintf('%s[%s]', $name, $child);
                    $formProperties[$childName] = $childSchema;
                    if (in_array($child, $childRequired, true)) {
                        $formRequired[] = $childName;
                    }
                }
                continue;
            }
            $formName = $type === 'array' ? $name . '[]' : $name;
            $formProperties[$formName] = $schema;
            if (in_array($name, $required, true)) {
                $formRequired[] = $formName;
            }
            if ($type === 'object') {
                $encoding[$formName] = ['style' => 'deepObject', 'explode' => true];
            }
        }

        $mediaType = ['schema' => $this->objectSchema($formProperties, $formRequired)];
        if ($encoding !== []) {
            $mediaType['encoding'] = $encoding;
        }

        return $mediaType;
    }

    /**
     * A form can carry a scalar, a list of scalars (`tags[]`) and an object of scalars (deepObject); OpenAPI 3.0 defines no
     * serialization for anything nested deeper.
     *
     * @param array<array-key, array<array-key, mixed>> $properties
     */
    private function formDescribable(array $properties): bool
    {
        foreach ($properties as $schema) {
            $type = $schema['type'] ?? null;
            if ($type === 'array' && self::isComposite(TypeHelper::toArray($schema['items'] ?? null))) {
                return false;
            }
            if ($type === 'object') {
                foreach (TypeHelper::toArray($schema['properties'] ?? null) as $child) {
                    if (self::isComposite(TypeHelper::toArray($child))) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private static function isComposite(array $schema): bool
    {
        return in_array($schema['type'] ?? null, ['array', 'object'], true);
    }

    /**
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>
     */
    private static function withoutNull(array $schema): array
    {
        unset($schema['nullable']);
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $schema['enum'] = array_values(array_filter($schema['enum'], static fn (mixed $value): bool => $value !== null));
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $child) {
                $schema['properties'][$name] = is_array($child) ? self::withoutNull($child) : $child;
            }
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::withoutNull($schema['items']);
        }

        return $schema;
    }
}
