<?php

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\ApiResponse;
use Tomaj\NetteApi\Link\ApiLink;

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
        return new ApiResponse(200, ['endpoints' => $endpoints]);
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
        $list = [];
        foreach ($this->apiDecider->getHandlers() as $handler) {
            $endpoint = $handler['endpoint'];
            if ($version && $version != $endpoint->getVersion()) {
                continue;
            }
            $item = [
                'method' => $endpoint->getMethod(),
                'version' => $endpoint->getVersion(),
                'package' => $endpoint->getPackage(),
                'api_action' => $endpoint->getApiAction(),
                'authorization' => get_class($handler['authorization']),
                'url' => $this->apiLink->link($endpoint),
            ];
            $params = $this->createParamsList($handler['handler']);
            if (count($params) > 0) {
                $item['params'] = $params;
            }
            $list[] = $item;
        }
        return $list;
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
        $paramsList = $handler->params();
        $params = [];
        foreach ($paramsList as $param) {
            $parameter = [
                'type' => $param->getType(),
                'key' => $param->getKey(),
                'is_required' => $param->isRequired(),
            ];
            if ($param->getAvailableValues()) {
                $parameter['available_values'] = $param->getAvailableValues();
            }
            $params[] = $parameter;
        }
        return $params;
    }
}
