<?php

namespace Tomaj\NetteApi\Handlers;

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

class SwaggerHandler extends BaseHandler
{
    /** @var ApiDecider */
    private $apiDecider;

    /** @var ApiLink */
    private $apiLink;

    /** @var Request */
    private $request;

    /**
     * ApiListingHandler constructor.
     *
     * @param ApiDecider  $apiDecider
     * @param ApiLink     $apiLink
     */
    public function __construct(ApiDecider $apiDecider, ApiLink $apiLink, Request $request)
    {
        parent::__construct();
        $this->apiDecider = $apiDecider;
        $this->apiLink = $apiLink;
        $this->request = $request;
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
        return 'Swagger API specification';
    }

    /**
     * {@inheritdoc}
     */
    public function tags()
    {
        return ['swagger', 'specification'];
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
            'swagger' => '2.0',
            'info' => [
                'title' => $this->apiDecider->getTitle(),
                'description' => $this->apiDecider->getDescription(),
                'version' => $version,
            ],
            'host' => $host,
            'schemes' => [
                $scheme,
            ],
            'securityDefinitions' => [
                'Bearer' => [
                    'type' => 'apiKey',
                    'name' => 'Authorization',
                    'in' => 'header'
                ],
            ],
            'basePath' => $basePath,
            'produces' => [
                'application/json'
            ],
            'responses' => $responses,
            'paths' => $this->getHandlersList($handlers, $baseUrl, $basePath),
        ];

        if ($params['format'] === 'yaml') {
            return new TextApiResponse(200, Yaml::dump($data, PHP_INT_MAX, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE), 'text/plain');
        }
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
                    $responses[$output->getCode()] = [
                        'description' => $output->getDescription(),
                        'schema' => json_decode($output->getSchema(), true),
                    ];
                }
            }

            $responses[400] = [
                'description' => 'Bad request',
                'schema' => [
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
            ];

            $responses[403] = [
                'description' => 'Operation forbidden',
                'schema' => [
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
            ];

            $responses[500] = [
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
            ];

            $settings = [
                'summary' => $handler['handler']->description(),
                'tags' => $handler['handler']->tags(),
                'parameters' => $this->createParamsList($handler['handler']),

            ];
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
        return array_map(function (InputParam $param) {
            $parameter = [
                'name' => $param->getKey(),
                'in' => $this->createIn($param->getType()),
                'required' => $param->isRequired(),
                'description' => $param->getDescription(),
            ];

            if ($param instanceof JsonInputParam) {
                $parameter['schema'] = json_decode($param->getSchema(), true);
            } else {
                $parameter['type'] = $param->isMulti() ? 'list' : 'string';
            }

            if ($param->getAvailableValues()) {
                $parameter['enum'] = $param->getAvailableValues();
            }
            return $parameter;
        }, $handler->params());
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
}
