<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\IResponse;
use Nette\Http\Request;
use Symfony\Component\Yaml\Yaml;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\OpenApi\SpecBuilder;
use Tomaj\NetteApi\OpenApi\SpecBuilderInterface;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Response\TextApiResponse;

class OpenApiHandler extends BaseHandler
{
    /**
     * OpenApiHandler constructor.
     * @param array<string,mixed> $initData - structured data for initialization response
     * @param array<string|int, mixed> $allowedEndpoints - list of endpoint (path and methods) which should be included in output, empty means all, wildcard * is supported for path
     *      Example: ['user/*' => ['get', 'post', 'delete'], 'user/list' => 'post', 'user/list' => [], 'user/list']
     * @param SpecBuilderInterface|null $specBuilder - builder of the spec, default builders are used if not set
     */
    public function __construct(
        private readonly ApiDecider $apiDecider,
        private readonly ApiLink $apiLink,
        private readonly Request $request,
        private readonly array $initData = [],
        private readonly array $allowedEndpoints = [],
        private ?SpecBuilderInterface $specBuilder = null,
    ) {
        parent::__construct();
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
            new GetInputParam('format')->setAvailableValues($availableFormats)->setDescription('Response format'),
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
