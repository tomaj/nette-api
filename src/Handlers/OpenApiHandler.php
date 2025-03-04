<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use InvalidArgumentException;
use Nette\Application\UI\InvalidLinkException;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Symfony\Component\Yaml\Yaml;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Misc\OpenApiTransform;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Output\RedirectOutput;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Response\TextApiResponse;

class OpenApiHandler extends BaseHandler
{
    /** @var ApiDecider */
    private $apiDecider;

    /** @var ApiLink */
    private $apiLink;

    /** @var Request */
    private $request;

    private $initData = [];

    private $definitions = [];

    /**
     * OpenApiHandler constructor.
     * @param ApiDecider $apiDecider
     * @param ApiLink $apiLink
     * @param Request $request
     * @param array $initData - structured data for initialization response
     */
    public function __construct(
        ApiDecider $apiDecider,
        ApiLink $apiLink,
        Request $request,
        array $initData = []
    ) {
        parent::__construct();
        $this->apiDecider = $apiDecider;
        $this->apiLink = $apiLink;
        $this->request = $request;
        $this->initData = $initData;
    }

    public function params(): array
    {
        $availableFormats = ['json'];
        if (class_exists(Yaml::class)) {
            $availableFormats[] = 'yaml';
        }
        return [
            (new GetInputParam('format'))->setAvailableValues($availableFormats)->setDescription('Response format'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Open API';
    }

    /**
     * {@inheritdoc}
     */
    public function tags(): array
    {
        return ['openapi'];
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $params): ResponseInterface
    {
        $version = $this->getEndpoint()->getVersion();
        $apis = $this->getApis($version);
        $scheme = $this->request->getUrl()->getScheme();
        $baseUrl = $this->request->getUrl()->getHostUrl();
        $basePath = $this->getBasePath($apis, $baseUrl);

        $securitySchemes = [];

        foreach ($apis as $api) {
            $authorization = $api->getAuthorization();
            if ($authorization instanceof BasicAuthentication) {
                $securitySchemes['Basic'] = [
                    'type' => 'http',
                    'scheme' => 'basic',
                ];
                continue;
            }
            if ($authorization instanceof BearerTokenAuthorization) {
                $securitySchemes['Bearer'] = [
                    'type' => 'http',
                    'scheme' => 'bearer',
                ];
                continue;
            }
            if ($authorization instanceof QueryApiKeyAuthentication) {
                $queryParamName = $authorization->getQueryParamName();
                $securitySchemes[$this->normalizeSecuritySchemeName('query', $queryParamName)] = [
                    'type' => 'apiKey',
                    'in' => 'query',
                    'name' => $queryParamName,
                ];
                continue;
            }
            if ($authorization instanceof HeaderApiKeyAuthentication) {
                $headerName = $authorization->getHeaderName();
                $securitySchemes[$this->normalizeSecuritySchemeName('header', $headerName)] = [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => $headerName,
                ];
                continue;
            }
            if ($authorization instanceof CookieApiKeyAuthentication) {
                $cookieName = $authorization->getCookieName();
                $securitySchemes[$this->normalizeSecuritySchemeName('cookie', $cookieName)] = [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => $cookieName,
                ];
                continue;
            }
        }

        $data = [
            'openapi' => '3.0.0',
            'info' => [
                'version' => (string)$version,
                'title' => 'Nette API',
            ],
            'servers' => [
                [
                    'url' => $baseUrl . $basePath,
                ],
            ],
            'components' => [
                'securitySchemes' => $securitySchemes,
                'schemas' => [
                    'ErrorWrongInput' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'enum' => ['error'],
                            ],
                            'message' => [
                                'type' => 'string',
                                'enum' => ['Wrong input'],
                            ],
                        ],
                        'required' => ['status', 'message'],
                    ],
                    'ErrorForbidden' => [
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
                ],
            ],

            'paths' => $this->getPaths($apis, $baseUrl, $basePath),
        ];

        if (!$securitySchemes) {
            unset($data['components']['securitySchemes']);
        }

        if (!empty($this->definitions)) {
            $data['components']['schemas'] = array_merge($this->definitions, $data['components']['schemas']);
        }

        $data = array_replace_recursive($data, $this->initData);

        if ($params['format'] === 'yaml') {
            return new TextApiResponse(IResponse::S200_OK, Yaml::dump($data, PHP_INT_MAX, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
        }
        return new JsonApiResponse(IResponse::S200_OK, $data);
    }

    private function getApis(string $version): array
    {
        return array_filter($this->apiDecider->getApis(), function (Api $api) use ($version) {
            return $version === $api->getEndpoint()->getVersion();
        });
    }

    /**
     * @param Api[] $versionApis
     * @param string $baseUrl
     * @param string $basePath
     * @return array
     * @throws InvalidLinkException
     */
    private function getPaths(array $versionApis, string $baseUrl, string $basePath): array
    {
        $list = [];
        foreach ($versionApis as $api) {
            $handler = $api->getHandler();
            $path = str_replace([$baseUrl, $basePath], '', $this->apiLink->link($api->getEndpoint()));
            $responses = [];

            $settings = [
                'summary' => $handler->summary(),
                'description' => $handler->description(),
                'tags' => $handler->tags(),
            ];

            if ($handler->deprecated()) {
                $settings['deprecated'] = true;
            }

            $parameters = $this->createParamsList($handler);
            $requestBody = $this->createRequestBody($handler);

            if (!empty($parameters) || !empty($requestBody)) {
                $responses[IResponse::S400_BAD_REQUEST] = [
                    'description' => 'Bad request',
                    'content' => [
                        'application/json; charset=utf-8' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ErrorWrongInput',
                            ],
                        ]
                    ],
                ];
            }

            $authorization = $api->getAuthorization();

            if (!$authorization instanceof NoAuthorization) {
                $responses[IResponse::S403_FORBIDDEN] = [
                    'description' => 'Operation forbidden',
                    'content' => [
                        'application/json; charset=utf-8' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ErrorForbidden',
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
                    $schema = $this->transformSchema(json_decode($output->getSchema(), true));
                    if (!isset($responses[$output->getCode()])) {
                        $responses[$output->getCode()] = [
                            'description' => $output->getDescription(),
                            'content' => [
                                'application/json; charset=utf-8' => [
                                    'schema' => $schema,
                                ],
                            ]
                        ];
                        if (!empty($examples = $output->getExamples())) {
                            if (count($examples) === 1) {
                                $example = is_array($output->getExample())? $output->getExample() : json_decode($output->getExample(), true);
                                $responses[$output->getCode()]['content']['application/json; charset=utf-8']['example'] = $example;
                            } else {
                                foreach ($examples as $exampleKey => $example) {
                                    $example = is_array($example)? $example : json_decode($example, true);
                                    $responses[$output->getCode()]['content']['application/json; charset=utf-8']['examples'][$exampleKey] = $example;
                                }
                            }
                        }
                    } else {
                        if (!isset($responses[$output->getCode()]['content']['application/json; charset=utf-8']['schema']['oneOf'])) {
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
                                ]
                            ],
                        ]
                    ];
                }
            }

            if (!empty($parameters)) {
                $settings['parameters'] = $parameters;
            }

            if (!empty($requestBody)) {
                $settings['requestBody'] = $requestBody;
            }

            if ($authorization instanceof BearerTokenAuthorization) {
                $settings['security'] = [
                    [
                        'Bearer' => [],
                    ],
                ];
            } elseif ($authorization instanceof BasicAuthentication) {
                $settings['security'] = [
                    [
                        'Basic' => [],
                    ],
                ];
            } elseif ($authorization instanceof QueryApiKeyAuthentication) {
                $settings['security'] = [
                    [
                        $this->normalizeSecuritySchemeName('query', $authorization->getQueryParamName()) => [],
                    ],
                ];
            } elseif ($authorization instanceof HeaderApiKeyAuthentication) {
                $settings['security'] = [
                    [
                        $this->normalizeSecuritySchemeName('header', $authorization->getHeaderName()) => [],
                    ],
                ];
            } elseif ($authorization instanceof CookieApiKeyAuthentication) {
                $settings['security'] = [
                    [
                        $this->normalizeSecuritySchemeName('cookie', $authorization->getCookieName()) => [],
                    ],
                ];
            }
            $settings['responses'] = $responses;
            $list[$path][strtolower($api->getEndpoint()->getMethod())] = $settings;
        }
        return $list;
    }

    private function getBasePath(array $apis, string $baseUrl): string
    {
        $basePath = '';
        foreach ($apis as $handler) {
            $basePath = $this->getLongestCommonSubstring($basePath, $this->apiLink->link($handler->getEndpoint()));
        }
        return rtrim(str_replace($baseUrl, '', $basePath), '/');
    }

    private function getLongestCommonSubstring($path1, $path2)
    {
        if ($path1 === null) {
            return $path2;
        }
        $commonSubstring = '';
        $shortest = min(strlen($path1), strlen($path2));
        for ($i = 0; $i <= $shortest; ++$i) {
            if (substr($path1, 0, $i) !== substr($path2, 0, $i)) {
                break;
            }
            $commonSubstring = substr($path1, 0, $i);
        }
        return $commonSubstring;
    }

    /**
     * Create array with params for specified handler
     *
     * @param ApiHandlerInterface $handler
     *
     * @return array
     */
    private function createParamsList(ApiHandlerInterface $handler)
    {
        $parameters = [];
        foreach ($handler->params() as $param) {
            if ($param->getType() !== InputParam::TYPE_GET && $param->getType() !== InputParam::TYPE_COOKIE) {
                continue;
            }

            $parameter = [
                'name' => $param->getKey() . ($param->isMulti() ? '[]' : ''),
                'in' => $this->createIn($param->getType()),
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

    private function createRequestBody(ApiHandlerInterface $handler)
    {
        $requestBody = [
            'properties' => [],
            'required' => [],
        ];
        $filesInBody = false;
        $requestBodyExample = [];
        foreach ($handler->params() as $param) {
            if ($param instanceof JsonInputParam) {
                $schema = json_decode($param->getSchema(), true);
                if (!empty($examples = $param->getExamples())) {
                    if (count($examples) === 1) {
                        $schema['example'] = is_array($param->getExample())? $param->getExample() : json_decode($param->getExample(), true);
                    } else {
                        foreach ($examples as $exampleKey => $example) {
                            $schema['examples'][$exampleKey] = is_array($example)? $example : json_decode($example, true);
                        }
                    }
                }
                return [
                    'description' => $param->getDescription(),
                    'required' => $param->isRequired(),
                    'content' => [
                        'application/json' => [
                            'schema' => $this->transformSchema($schema),
                        ],
                    ],
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
                return [
                    'description' => $param->getDescription(),
                    'required' => $param->isRequired(),
                    'content' => [
                        'text/plain' => [
                            'schema' => $schema,
                        ],
                    ],
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
                            $descriptionParts[] = ' * `' . $availableKey . '` - ' . $availableValue;
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
            return [
                'required' => true,
                'content' => [
                    $contentType => [
                        'schema' => $requestBodySchema,
                    ],
                ],
            ];
        }

        return null;
    }

    private function createIn($type)
    {
        if ($type == InputParam::TYPE_GET) {
            return 'query';
        }
        if ($type == InputParam::TYPE_COOKIE) {
            return 'cookie';
        }
        return 'body';
    }

    private function transformSchema(array $schema)
    {
        OpenApiTransform::transformTypes($schema);

        if (isset($schema['definitions'])) {
            foreach ($schema['definitions'] as $name => $definition) {
                $this->addDefinition($name, $this->transformSchema($definition));
            }
            unset($schema['definitions']);
        }
        return json_decode(str_replace('#/definitions/', '#/components/schemas/', json_encode($schema, JSON_UNESCAPED_SLASHES)), true);
    }

    private function addDefinition($name, $definition)
    {
        if (isset($this->definitions[$name]) && $this->definitions[$name] !== $definition) {
            throw new InvalidArgumentException('Definition with name ' . $name . ' already exists. Rename it.');
        }
        $this->definitions[$name] = $definition;
    }

    private function normalizeSecuritySchemeName(string $type, string $name): string
    {
        return 'api_key__' . $type . '__' . strtolower(str_replace('-', '_', $name));
    }
}
