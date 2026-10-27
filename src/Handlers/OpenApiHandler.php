<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\IResponse;
use Nette\Http\Request;
use Symfony\Component\Yaml\Yaml;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\OpenApi\SpecBuilder;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\InputParam;
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

    /** @var array<string,mixed> */
    private $initData = [];

    /** @var array<string|int, mixed> */
    private $allowedEndpoints = [];

    /** @var SpecBuilder|null */
    private $specBuilder;

    /**
     * OpenApiHandler constructor.
     * @param array<string,mixed> $initData - structured data for initialization response
     * @param array<string|int, mixed> $allowedEndpoints - list of endpoint (path and methods) which should be included in output, empty means all, wildcard * is supported for path
     *      Example: ['user/*' => ['get', 'post', 'delete'], 'user/list' => 'post', 'user/list' => [], 'user/list']
     * @param SpecBuilder|null $specBuilder - builder of the spec, default builders are used if not set
     */
    public function __construct(
        ApiDecider $apiDecider,
        ApiLink $apiLink,
        Request $request,
        array $initData = [],
        array $allowedEndpoints = [],
        ?SpecBuilder $specBuilder = null,
    ) {
        parent::__construct();
        $this->apiDecider = $apiDecider;
        $this->apiLink = $apiLink;
        $this->request = $request;
        $this->initData = $initData;
        $this->allowedEndpoints = $allowedEndpoints;
        $this->specBuilder = $specBuilder;
    }

    /**
     * @return InputParam[]
     */
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
     * @return string[]
     */
    public function tags(): array
    {
        return ['openapi'];
    }

    /**
     * {@inheritdoc}
     * @param array<string,mixed> $params
     */
    public function handle(array $params): ResponseInterface
    {
        $this->specBuilder ??= new SpecBuilder();
        $data = $this->specBuilder->build(
            $this->apiDecider->getApis(),
            $this->getEndpoint()?->getVersion(),
            $this->request->getUrl()->getHostUrl(),
            $this->apiLink,
            $this->initData,
            $this->allowedEndpoints,
        );

        if ($params['format'] === 'yaml') {
            return new TextApiResponse(IResponse::S200_OK, Yaml::dump($data, PHP_INT_MAX, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
        }

        return new JsonApiResponse(IResponse::S200_OK, $data);
    }
}
