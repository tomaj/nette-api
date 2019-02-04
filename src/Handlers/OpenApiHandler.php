<?php

namespace Tomaj\NetteApi\Handlers;

use InvalidArgumentException;
use JSONSchemaFaker\Faker;
use Nette\Http\Request;
use Symfony\Component\Yaml\Yaml;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\TextApiResponse;
use Tracy\Debugger;

class OpenApiHandler extends BaseHandler
{
    /** @var ApiDecider */
    private $apiDecider;

    /** @var ApiLink */
    private $apiLink;

    /** @var Request */
    private $request;

    private $initData = [];

    private $faker;

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
        $this->faker = new Faker();
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'format', InputParam::OPTIONAL, ['json', 'yaml'], false, 'Response format')
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function description()
    {
        return 'Open API';
    }

    /**
     * {@inheritdoc}
     */
    public function tags()
    {
        return ['openapi'];
    }

    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        $version = $this->getEndpoint()->getVersion();
        $handlers = $this->getHandlers($version);
        $scheme = $this->request->getUrl()->getScheme();
        $host = $this->request->getUrl()->getHost();
        $baseUrl = $scheme . '://' . $host;
        $basePath = $this->getBasePath($handlers, $baseUrl);

        $responses = [
            404 => [
                'description' => 'Not found',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['error'],
                        ],
                        'message' => [
                            'type' => 'string',
                            'enum' => ['Unknown api endpoint'],
                        ],
                    ],
                    'required' => ['status', 'message'],
                ],
            ],
            500 => [
                'description' => 'Internal server error',
                'schema' => [
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
        ];

        $data = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->apiDecider->getTitle(),
                'description' => $this->apiDecider->getDescription(),
                'version' => $version,
            ],
            'servers' => [
                [
                    'url' => $scheme . '://' . $host . $basePath,
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'Bearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
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
                                'enum' => ['Authorization header HTTP_Authorization is not set', 'Authorization header contains invalid structure'],
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

            'paths' => $this->getHandlersList($handlers, $baseUrl, $basePath),
        ];

        if (!empty($this->definitions)) {
            $data['components']['schemas'] = array_merge($this->definitions, $data['components']['schemas']);
        }

        if ($params['format'] === 'yaml') {
            return new TextApiResponse(200, Yaml::dump($data, PHP_INT_MAX, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE), 'text/plain');
        }

        $data = array_merge_recursive($this->initData, $data);
        return new JsonApiResponse(200, $data);
    }

    /**
     * @param int $version
     * @return []
     */
    private function getHandlers($version)
    {
        $versionHandlers = array_filter($this->apiDecider->getHandlers(), function ($handler) use ($version) {
            return $version == $handler['endpoint']->getVersion();
        });
        return $versionHandlers;
    }

    /**
     * Create handler list for specified version
     *
     * @param array $versionHandlers
     * @param string $basePath
     *
     * @return array
     */
    private function getHandlersList($versionHandlers, $baseUrl, $basePath)
    {
        $list = [];
        foreach ($versionHandlers as $handler) {
            $path = str_replace([$baseUrl, $basePath], '', $this->apiLink->link($handler['endpoint']));

            $responses = [];
            foreach ($handler['handler']->outputs() as $output) {
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
            }

            $responses[400] = [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorWrongInput',
                        ],
                    ]
                ],
            ];

            $responses[403] = [
                'description' => 'Operation forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorForbidden',
                        ],
                    ],
                ],
            ];

            $responses[500] = [
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
                'summary' => $handler['handler']->description(),
                'tags' => $handler['handler']->tags(),
            ];

            $parameters = $this->createParamsList($handler['handler']);
            if (!empty($parameters)) {
                $settings['parameters'] = $parameters;
            }

            $requestBody = $this->createRequestBody($handler['handler']);
            if (!empty($requestBody)) {
                $settings['requestBody'] = $requestBody;
            }

            if ($handler['authorization'] instanceof BearerTokenAuthorization) {
                $settings['security'] = [
                    [
                        'Bearer' => [],
                    ],
                ];
            }
            $settings['responses'] = $responses;
            $list[$path][strtolower($handler['endpoint']->getMethod())] = $settings;
        }
        return $list;
    }

    private function getBasePath($handlers, $baseUrl)
    {
        $basePath = null;
        foreach ($handlers as $handler) {
            $basePath = $this->getLongestCommonSubstring($basePath, $this->apiLink->link($handler['endpoint']));
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
            if ($param instanceof JsonInputParam) {
                continue;
            }

            $parameter = [
                'name' => $param->getKey(),
                'in' => $this->createIn($param->getType()),
                'required' => $param->isRequired(),
                'description' => $param->getDescription(),
                'schema' => [
                    'type' => $param->isMulti() ? 'array' : 'string',
                ],
            ];
            if ($param->getAvailableValues()) {
                $parameter['schema']['enum'] = $param->getAvailableValues();
            }
        }
        return $parameters;
    }

    private function createRequestBody(ApiHandlerInterface $handler)
    {
        foreach ($handler->params() as $param) {
            if ($param instanceof JsonInputParam) {
                return [
                    'description' => $param->getDescription(),
                    'required' => $param->isRequired(),
                    'content' => [
                        'application/json' => [
                            'schema' => $this->transformSchema(json_decode($param->getSchema(), true)),
                        ],
                    ],
                ];
            }
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
        $originalSchema = json_decode(json_encode($schema));
        $this->transformTypes($schema);

        if (isset($schema['definitions'])) {
            foreach ($schema['definitions'] as $name => $definition) {
                $this->addDefinition($name, $definition);
            }
            unset($schema['definitions']);
            $schema = json_decode(str_replace('#/definitions/', '#/components/schemas/', json_encode($schema, JSON_UNESCAPED_SLASHES)), true);
        }
        $schema['example'] = json_decode(json_encode($this->faker->generate($originalSchema)), true);
        return $schema;
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
}
