<?php

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\Request;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;

class SwaggerHandler extends BaseHandler
{
    /** @var ApiDecider */
    private $apiDecider;

    /** @var ApiLink */
    private $apiLink;

    /** @var Request */
    private $request;

    /** @var string */
    private $basePath;

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
        $data = [
            'swagger' => '2.0', // ?
            'info' => [
                'title' => $this->apiDecider->getTitle(),
                'description' => $this->apiDecider->getDescription(),
                'version' => $version,
            ],
            'host' => $this->request->getUrl()->getHost(),
            'schemes' => [
                $this->request->getUrl()->getScheme(),
            ],
            'basePath' => $this->getBasePath($handlers),
            'produces' => [
                'application/json'
            ],
            'paths' => $this->getHandlersList($handlers),
        ];
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
     *
     * @return array
     */
    private function getHandlersList($versionHandlers)
    {
        $list = [];
        $baseUrl = $this->request->getUrl()->getScheme() . '://' . $this->request->getUrl()->getHost() . $this->basePath;
        foreach ($versionHandlers as $handler) {
            $path = str_replace($baseUrl, '', $this->apiLink->link($handler['endpoint']));

            $responses = [];
            foreach ($handler->outputs() as $output) {
                if ($output instanceof JsonOutput) {
                    $responses[$output->getCode()] = [
                        'description' => $output->getDescription(),
                        'schema' => $output->getSchema(),
                    ];
                }
            }

            $responses[403] = [
                'description' => 'Operation forbidden',
            ];
            $responses[500] = [
                'description' => 'Wrong input',
            ];

            $list[$path][strtolower($handler['endpoint']->getMethod())] = [
                'summary' => $handler['handler']->description(),
                'operationId' => get_class($handler['handler']), // TODO zistit co to moze byt,
                'tags' => $handler['handler']->tags(),
                'parameters' => $this->createParamsList($handler['handler']),
                'responses' => $responses,
            ];
        }
        return $list;
    }

    private function getBasePath($handlers)
    {
        $baseUrl = $this->request->getUrl()->getScheme() . '://' . $this->request->getUrl()->getHost();
        foreach ($handlers as $handler) {
            if (!$handler instanceof SwaggerHandler) {
                $link = $this->apiLink->link($handler['endpoint']);
                break;
            }
        }
        $commonPath = '';
        $actualPath = $this->request->getUrl()->getPath();
        $link = str_replace($baseUrl, '', $link);
        for ($i = 0; $i < strlen($link); $i++) {
            if ($link[$i] != $actualPath[$i]) {
                break;
            }
            if ($link[$i] == $actualPath[$i]) {
                $commonPath .= $link[$i];
            }
        }
        $this->basePath = rtrim($commonPath, '/');
        return $this->basePath;
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
                $parameter['schema'] = $param->getSchema();
            } else {
                $parameter['type'] = $param->getAvailableValues() ? 'list' : 'string';
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
