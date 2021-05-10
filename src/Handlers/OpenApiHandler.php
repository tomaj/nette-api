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
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\Link\ApiLink;
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
        return [
            (new GetInputParam('format'))->setAvailableValues(['json', 'yaml'])->setDescription('Response format'),
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
        $host = $this->request->getUrl()->getHost();
        $baseUrl = $scheme . '://' . $host;
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
                    'url' => $scheme . '://' . $host . $basePath,
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

    private function getApis(int $version): array
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
            foreach ($handler->outputs() as $output) {
                if ($output instanceof JsonOutput) {
                    $schema = $this->transformSchema(json_decode($output->getSchema(), true));
                    $responses[$output->getCode()] = [
                        'description' => $output->getDescription(),
                        'content' => [
                            'application/json' => [
                                'schema' => $schema,
                            ],
                        ]
                    ];
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

            $responses[IResponse::S400_BAD_REQUEST] = [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorWrongInput',
                        ],
                    ]
                ],
            ];

            $responses[IResponse::S403_FORBIDDEN] = [
                'description' => 'Operation forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorForbidden',
                        ],
                    ],
                ],
            ];

            $responses[IResponse::S500_INTERNAL_SERVER_ERROR] = [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/InternalServerError',
                        ],
                    ],
                ],
            ];

            $settings = [
                'summary' => $handler->summary(),
                'description' => $handler->description(),
                'tags' => $handler->tags(),
            ];

            foreach ($handler->additionalData() as $additionalDataKey => $additionalDataValue) {
                $settings['x-' . $additionalDataKey] = $additionalDataValue;
            }

            if ($handler->deprecated()) {
                $settings['deprecated'] = true;
            }

            $parameters = $this->createParamsList($handler);
            if (!empty($parameters)) {
                $settings['parameters'] = $parameters;
            }

            $requestBody = $this->createRequestBody($handler);
            if (!empty($requestBody)) {
                $settings['requestBody'] = $requestBody;
            }

            $authorization = $api->getAuthorization();
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
            if ($param->getType() !== InputParam::TYPE_GET) {
                continue;
            }

            $schema = [
                'type' => $param->isMulti() ? 'array' : 'string',
            ];

            $parameter = [
                'name' => $param->getKey() . ($param->isMulti() ? '[]' : ''),
                'in' => $this->createIn($param->getType()),
                'required' => $param->isRequired(),
                'description' => $param->getDescription(),
            ];

            if ($param->isMulti()) {
                $schema['items'] = ['type' => 'string'];
            }
            if ($param->getAvailableValues()) {
                $schema['enum'] = $param->getAvailableValues();
            }
            if ($param->getExample() || $param->getDefault()) {
                $schema['example'] = $param->getExample() ?: $param->getDefault();
            }

            $parameter['schema'] = $schema;

            $parameters[] = $parameter;
        }
        return $parameters;
    }

    private function createRequestBody(ApiHandlerInterface $handler)
    {
        $postParams = [
            'properties' => [],
            'required' => [],
        ];
        $postParamsExample = [];
        foreach ($handler->params() as $param) {
            if ($param instanceof JsonInputParam) {
                $schema = json_decode($param->getSchema(), true);
                if ($param->getExample()) {
                    $schema['example'] = $param->getExample();
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
                return [
                    'description' => $param->getDescription(),
                    'required' => $param->isRequired(),
                    'content' => [
                        'text/plain' => [
                            'schema' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ];
            }
            if ($param->getType() === InputParam::TYPE_POST) {
                $property = [
                    'type' => $param->isMulti() ? 'array' : 'string',
                    'description' => $param->getDescription(),
                ];
                if ($param->isMulti()) {
                    $property['items'] = ['type' => 'string'];
                }
                if ($param->getAvailableValues()) {
                    $property['enum'] = $param->getAvailableValues();
                }

                $postParams['properties'][$param->getKey() . ($param->isMulti() ? '[]' : '')] = $property;
                if ($param->isRequired()) {
                    $postParams['required'][] = $param->getKey() . ($param->isMulti() ? '[]' : '');
                }

                if ($param->getExample() || $param->getDefault()) {
                    $postParamsExample[$param->getKey()] = $param->getExample() ?: $param->getDefault();
                }
            }
        }

        if (!empty($postParams['properties'])) {
            $postParamsSchema = [
                'type' => 'object',
                'properties' => $postParams['properties'],
                'required' => $postParams['required'],
            ];

            if ($postParamsExample) {
                $postParamsSchema['example'] = $postParamsExample;
            }

            return [
                'required' => true,
                'content' => [
                    'application/x-www-form-urlencoded' => [
                        'schema' => $postParamsSchema,
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
        $this->transformTypes($schema);

        if (isset($schema['definitions'])) {
            foreach ($schema['definitions'] as $name => $definition) {
                $this->addDefinition($name, $this->transformSchema($definition));
            }
            unset($schema['definitions']);
        }
        return json_decode(str_replace('#/definitions/', '#/components/schemas/', json_encode($schema, JSON_UNESCAPED_SLASHES)), true);
    }

    private function transformTypes(array &$schema)
    {
        foreach ($schema as $key => &$value) {
            if ($key === 'type' && is_array($value)) {
                if (count($value) === 2 && in_array('null', $value)) {
                    unset($value[array_search('null', $value)]);
                    $value = implode(',', $value);
                    $schema['nullable'] = true;
                } else {
                    throw new InvalidArgumentException('Type cannot be array and if so, one element have to be "null"');
                }
            } elseif (is_array($value)) {
                $this->transformTypes($value);
            }
        }
    }

    private function addDefinition($name, $definition)
    {
        if (isset($this->definitions[$name])) {
            throw new InvalidArgumentException('Definition with name ' . $name . ' already exists. Rename it or use existing one.');
        }
        $this->definitions[$name] = $definition;
    }

    private function normalizeSecuritySchemeName(string $type, string $name): string
    {
        return 'api_key__' . $type . '__' . strtolower(str_replace('-', '_', $name));
    }
}
