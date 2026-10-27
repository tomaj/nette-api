<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Params\InputParam;

/**
 * Creates operation parameters from query (GET) and cookie params of handler
 */
class ParameterBuilder implements ParameterBuilderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(ApiHandlerInterface $handler): array
    {
        $parameters = [];
        foreach ($handler->params() as $param) {
            if ($param->getType() !== InputParam::TYPE_GET && $param->getType() !== InputParam::TYPE_COOKIE) {
                continue;
            }

            $parameter = [
                'name' => $param->getKey() . ($param->isMulti() ? '[]' : ''),
                'in' => $param->getType() === InputParam::TYPE_GET ? 'query' : 'cookie',
                'required' => $param->isRequired(),
            ];

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
                        $descriptionParts[] = ' * `' . $availableKey . '` - ' . $availableValue;
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
