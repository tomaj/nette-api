<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\ApiResponse;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ApiListingHandler extends BaseHandler
{
    /**
     * @var ApiDecider
     */
    private $apiDecider;

    /**
     * @var ApiLink
     */
    private $apiLink;

    /**
     * ApiListingHandler constructor.
     *
     * @param ApiDecider  $apiDecider
     * @param ApiLink     $apiLink
     */
    public function __construct(ApiDecider $apiDecider, ApiLink $apiLink)
    {
        parent::__construct();
        $this->apiDecider = $apiDecider;
        $this->apiLink = $apiLink;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($params)
    {
        $version = $this->getEndpoint()->getVersion();
        $endpoints = $this->getHandlersList($version);
        return new JsonApiResponse(200, ['endpoints' => $endpoints]);
    }

    /**
     * Create handler list for specified version
     *
     * @param integer $version
     *
     * @return array
     */
    private function getHandlersList($version)
    {
        $versionHandlers = array_filter($this->apiDecider->getHandlers(), function ($handler) use ($version) {
            return $version == $handler['endpoint']->getVersion();
        });

        return array_map(function ($handler) {
            return [
                'method' => $handler['endpoint']->getMethod(),
                'version' => $handler['endpoint']->getVersion(),
                'package' => $handler['endpoint']->getPackage(),
                'api_action' => $handler['endpoint']->getApiAction(),
                'authorization' => get_class($handler['authorization']),
                'url' => $this->apiLink->link($handler['endpoint']),
                'params' => $this->createParamsList($handler['handler']),
            ];
        }, $versionHandlers);
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
                'type' => $param->getType(),
                'key' => $param->getKey(),
                'is_required' => $param->isRequired(),
            ];
            if ($param->getAvailableValues()) {
                $parameter['available_values'] = $param->getAvailableValues();
            }
            return $parameter;
        }, $handler->params());
    }
}
