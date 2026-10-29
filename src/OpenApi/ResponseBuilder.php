<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Nette\Http\IResponse;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Output\RedirectOutput;

/**
 * Creates operation responses: default error responses and responses from handler outputs
 */
class ResponseBuilder implements ResponseBuilderInterface
{
    private const string JSON_CONTENT_TYPE = 'application/json; charset=utf-8';

    private const string PROBLEM_CONTENT_TYPE = 'application/problem+json; charset=utf-8';

    /**
     * @param bool $problemDetails describe error responses as RFC 9457 application/problem+json
     *                             (use together with ProblemDetailsErrorHandler)
     */
    public function __construct(private readonly bool $problemDetails = false)
    {
    }

    /**
     * Schemas referenced by default error responses, they are added to components/schemas
     *
     * @return array<string, array<string, mixed>>
     */
    #[\Override]
    public function buildErrorSchemas(): array
    {
        if ($this->problemDetails) {
            return ['ProblemDetails' => self::problemDetailsSchema()];
        }

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
    #[\Override]
    public function build(ApiHandlerInterface $handler, ApiAuthorizationInterface $authorization, bool $hasInput, SchemaRegistry $schemaRegistry): array
    {
        $responses = [];
        if ($hasInput) {
            $responses[IResponse::S400_BAD_REQUEST] = $this->errorResponse('Bad request', 'ErrorWrongInput');
        }

        if (!$authorization instanceof NoAuthorization) {
            $responses[IResponse::S401_Unauthorized] = $this->errorResponse('Operation forbidden', 'ErrorUnauthorized');
        }

        $responses[IResponse::S500_INTERNAL_SERVER_ERROR] = $this->errorResponse('Internal server error', 'InternalServerError');

        foreach ($handler->outputs() as $output) {
            if ($output instanceof JsonOutput) {
                $schema = $schemaRegistry->transform(TypeHelper::toArray(json_decode($output->getSchema(), true)));
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
                    $responses[$output->getCode()] = $this->appendOneOfSchema($responses[$output->getCode()], $schema);
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

    /**
     * RFC 9457 problem details object
     *
     * @return array<string, mixed>
     */
    public static function problemDetailsSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'RFC 9457 problem details',
            'properties' => [
                'type' => ['type' => 'string', 'format' => 'uri-reference', 'default' => 'about:blank'],
                'status' => ['type' => 'integer', 'minimum' => 400, 'maximum' => 599],
                'title' => ['type' => 'string'],
                'detail' => ['type' => 'string'],
                'instance' => ['type' => 'string', 'format' => 'uri-reference'],
                'errors' => [
                    'type' => 'array',
                    'description' => 'Validation errors (400)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'parameter' => ['type' => 'string'],
                            'detail' => ['type' => 'string'],
                        ],
                        'required' => ['parameter', 'detail'],
                    ],
                ],
                'outputErrors' => [
                    'type' => 'array',
                    'description' => 'Output schema errors, one list per declared output (500 invalid-output, only with error details on)',
                    'items' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
            'required' => ['type', 'status', 'title'],
        ];
    }

    /**
     * @param string $legacySchema schema name used without problem details
     * @return array<string, mixed>
     */
    private function errorResponse(string $description, string $legacySchema): array
    {
        $contentType = $this->problemDetails ? self::PROBLEM_CONTENT_TYPE : self::JSON_CONTENT_TYPE;
        $schema = $this->problemDetails ? 'ProblemDetails' : $legacySchema;
        return [
            'description' => $description,
            'content' => [
                $contentType => [
                    'schema' => [
                        '$ref' => '#/components/schemas/' . $schema,
                    ],
                ],
            ],
        ];
    }

    /**
     * Adds another schema to an already defined JSON response, wrapping the existing schema to `oneOf`.
     *
     * @param array<string, mixed> $response
     * @param array<array-key, mixed>|null $schema
     * @return array<string, mixed>
     */
    private function appendOneOfSchema(array $response, ?array $schema): array
    {
        $contentType = self::JSON_CONTENT_TYPE;
        $content = TypeHelper::toArray($response['content'] ?? []);
        $jsonContent = TypeHelper::toArray($content[$contentType] ?? []);
        $existingSchema = $jsonContent['schema'] ?? null;

        if (is_array($existingSchema) && isset($existingSchema['oneOf']) && is_array($existingSchema['oneOf'])) {
            $existingSchema['oneOf'][] = $schema;
            $jsonContent['schema'] = $existingSchema;
        } else {
            unset($jsonContent['schema']);
            $jsonContent['schema'] = ['oneOf' => [$existingSchema, $schema]];
        }

        $content[$contentType] = $jsonContent;
        $response['content'] = $content;
        return $response;
    }
}
