<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Nette\Http\IResponse;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Output\RedirectOutput;

/**
 * Creates operation responses: default error responses and responses from handler outputs
 */
class ResponseBuilder
{
    /**
     * Schemas referenced by default error responses, they are added to components/schemas
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildErrorSchemas(): array
    {
        return [
            'ErrorWrongInput' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['error'],
                    ],
                    'message' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['status', 'message'],
            ],
            'ErrorUnauthorized' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['error'],
                    ],
                    'message' => [
                        'type' => 'string',
                        'enum' => ['Authorization header HTTP_Authorization is not set', 'Authorization header contains invalid structure', 'Authorization header doesn\'t contain bearer token', 'API key is not set'],
                    ],
                ],
                'required' => ['status', 'message'],
            ],
            'InternalServerError' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['error'],
                    ],
                    'message' => [
                        'type' => 'string',
                        'enum' => ['Internal server error'],
                    ],
                ],
                'required' => ['status', 'message'],
            ],
        ];
    }

    /**
     * @param bool $hasInput true if handler has params (adds 400 Bad request response)
     * @return array<int, array<string, mixed>>
     */
    public function build(ApiHandlerInterface $handler, ApiAuthorizationInterface $authorization, bool $hasInput, SchemaRegistry $schemaRegistry): array
    {
        $responses = [];
        if ($hasInput) {
            $responses[IResponse::S400_BAD_REQUEST] = [
                'description' => 'Bad request',
                'content' => [
                    'application/json; charset=utf-8' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorWrongInput',
                        ],
                    ],
                ],
            ];
        }

        if (!$authorization instanceof NoAuthorization) {
            $responses[IResponse::S401_Unauthorized] = [
                'description' => 'Operation forbidden',
                'content' => [
                    'application/json; charset=utf-8' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorUnauthorized',
                        ],
                    ],
                ],
            ];
        }

        $responses[IResponse::S500_INTERNAL_SERVER_ERROR] = [
            'description' => 'Internal server error',
            'content' => [
                'application/json; charset=utf-8' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/InternalServerError',
                    ],
                ],
            ],
        ];

        foreach ($handler->outputs() as $output) {
            if ($output instanceof JsonOutput) {
                $schema = $schemaRegistry->transform(json_decode($output->getSchema(), true));
                if (!isset($responses[$output->getCode()])) {
                    $responses[$output->getCode()] = [
                        'description' => $output->getDescription(),
                        'content' => [
                            'application/json; charset=utf-8' => [
                                'schema' => $schema,
                            ],
                        ],
                    ];
                    if (!empty($examples = $output->getExamples())) {
                        if (count($examples) === 1) {
                            $example = is_string($output->getExample()) ? json_decode($output->getExample(), true) : $output->getExample();
                            $responses[$output->getCode()]['content']['application/json; charset=utf-8']['example'] = $example;
                        } else {
                            foreach ($examples as $exampleKey => $example) {
                                $example = is_string($example) ? json_decode($example, true) : $example;
                                $responses[$output->getCode()]['content']['application/json; charset=utf-8']['examples'][$exampleKey] = $example;
                            }
                        }
                    }
                } else {
                    if (!isset($responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema']['oneOf'])) {
                        /** @phpstan-ignore-next-line */
                        $tmp = $responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema'];
                        unset($responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema']);
                        $responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema'] = [
                            'oneOf' => [],
                        ];
                        $responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema']['oneOf'][] = $tmp;
                    }

                    $responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema']['oneOf'][] = $schema;
                }
            }

            if ($output instanceof RedirectOutput) {
                $responses[$output->getCode()] = [
                    'description' => 'Redirect',
                    'headers' => [
                        'Location' => [
                            'description' => $output->getDescription(),
                            'schema' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ];
            }
        }

        return $responses;
    }
}
