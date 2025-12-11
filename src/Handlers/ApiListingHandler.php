<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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
    public function handle(array $params): ResponseInterface
    {
        $version = $this->getEndpoint()?->getVersion();
        $endpoints = $this->getApiList($version ?? '');
        return new JsonApiResponse(200, ['endpoints' => $endpoints]);
    }

    /**
     * Create handler list for specified version
     *
     *
     * @return array<string,mixed>
     */
    private function getApiList(string $version): array
    {
        $versionApis = array_filter($this->apiDecider->getApis(), function (Api $api) use ($version) {
            return $version === $api->getEndpoint()->getVersion();
        });

        return array_map(function (Api $api) {
            return [
                'method' => $api->getEndpoint()->getMethod(),
                'version' => $api->getEndpoint()->getVersion(),
                'package' => $api->getEndpoint()->getPackage(),
                'api_action' => $api->getEndpoint()->getApiAction(),
                'authorization' => get_class($api->getAuthorization()),
                'url' => $this->apiLink->link($api->getEndpoint()),
                'params' => $this->createParamsList($api->getHandler()),
            ];
        }, $versionApis);
    }

    /**
     * Create array with params for specified handler
     *
     *
     * @return array{type: string, key: string, is_required: bool, available_values?: non-empty-array<string, mixed>}
     */
    private function createParamsList(ApiHandlerInterface $handler): array
    {
        /** @phpstan-ignore-next-line */
        return array_map(function (InputParam $param): array {
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
