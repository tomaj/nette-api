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
        foreach ($handler->params() as $param) {
            if ($param instanceof JsonInputParam) {
                $schema = TypeHelper::toArray(json_decode($param->getSchema(), true));
                if (!empty($examples = $param->getExamples())) {
                    if (count($examples) === 1) {
                        $schema['example'] = is_string($param->getExample()) ? json_decode($param->getExample(), true) : $param->getExample();
                    } else {
                        $schemaExamples = [];
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

        return $result ?: null;
    }
}
