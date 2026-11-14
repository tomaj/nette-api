<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
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
        $typedAnyRequired = false;
        $typedFiles = [];
        foreach ($handler->params() as $param) {
            if ($param instanceof InputParam && $param->getOpenApiSchema() !== null && ($param->getType() === InputParam::TYPE_BODY || $param->getType() === InputParam::TYPE_FILE)) {
                $schema = $param->getOpenApiSchema();
                if ($param->getDescription() !== '' && !isset($schema['description'])) {
                    $schema['description'] = $param->getDescription();
                }
                $typedProperties[$param->getKey()] = $schema;
                if ($param->getType() === InputParam::TYPE_FILE) {
                    $typedFiles[] = $param->getKey();
                }
                if ($param->isRequired()) {
                    $typedRequired[] = $param->getKey();
                    $typedAnyRequired = true;
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
            $typedSchema = [
                'type' => 'object',
                'properties' => $typedProperties,
            ];
            if ($typedRequired !== []) {
                $typedSchema['required'] = $typedRequired;
            }
            // files can only be sent as multipart/form-data, so the other media types do not list them
            $withoutFiles = ['type' => 'object'];
            $propertiesWithoutFiles = array_diff_key($typedProperties, array_flip($typedFiles));
            if ($propertiesWithoutFiles !== []) {
                $withoutFiles['properties'] = $propertiesWithoutFiles;
            }
            // the other media types are advertised only when no file is required, so `typedRequired` has none
            if ($typedRequired !== []) {
                $withoutFiles['required'] = $typedRequired;
            }
            $result['required'] = $typedAnyRequired;
            // a required file cannot be sent as JSON, so such a request could only be answered with 400: only multipart is advertised
            $advertised = array_intersect($typedRequired, $typedFiles) !== []
                ? ['multipart/form-data']
                : ($typedTypes === [] ? ['application/json', 'application/x-www-form-urlencoded'] : $typedTypes);
            foreach ($advertised as $contentType) {
                $result['content'][$contentType] = ['schema' => $contentType === 'multipart/form-data' ? $typedSchema : $withoutFiles];
            }
        }

        return $result ?: null;
    }
}
