<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Params\InputParam;

/**
 * Creates operation parameters from query (GET) and cookie params of handler
 */
class ParameterBuilder implements ParameterBuilderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function build(ApiHandlerInterface $handler): array
    {
        $parameters = [];
        foreach ($handler->params() as $param) {
            $in = match ($param->getType()) {
                InputParam::TYPE_GET => 'query',
                InputParam::TYPE_COOKIE => 'cookie',
                InputParam::TYPE_PATH => 'path',
                InputParam::TYPE_HEADER => 'header',
                default => null,
            };
            if ($in === null) {
                continue;
            }

            $explicitSchema = $param instanceof InputParam ? $param->getOpenApiSchema() : null;
            $parameter = [
                // PHP reads a repeated query value only in the bracket form, for example items[]=1&items[]=2
                'name' => $param->getKey() . ($param->isMulti() && ($explicitSchema === null || ($explicitSchema['type'] ?? null) === 'array') ? '[]' : ''),
                'in' => $in,
                'required' => $in === 'path' ? true : $param->isRequired(),
            ];

            if ($explicitSchema !== null) {
                $parameter['schema'] = $explicitSchema;
                if ($param->getDescription() !== '') {
                    $parameter['description'] = $param->getDescription();
                }
                if ($param->getExamples() !== []) {
                    $parameter['example'] = $param->getExample();
                }
                $parameters[] = $parameter;
                continue;
            }

            $schema = [
                'type' => $param->isMulti() ? 'array' : 'string',
            ];
            if ($param->isMulti()) {
                $schema['items'] = ['type' => 'string'];
            }

            $descriptionParts = [];
            if ($param->getDescription()) {
                $descriptionParts[] = $param->getDescription();
            }

            $availableValues = $param->getAvailableValues();
            if ($availableValues) {
                $schema['enum'] = array_keys($availableValues);
                if (array_keys($availableValues) !== array_values($availableValues)) {
                    foreach ($availableValues as $availableKey => $availableValue) {
                        $descriptionParts[] = ' * `' . $availableKey . '` - ' . TypeHelper::toString($availableValue);
                    }
                }
            }

            $parameter['schema'] = $schema;
            if ($descriptionParts !== []) {
                $parameter['description'] = implode("\n", $descriptionParts);
            }

            if ($param->getExample() || $param->getDefault()) {
                $parameter['example'] = $param->getExample() ?: $param->getDefault();
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }
}
