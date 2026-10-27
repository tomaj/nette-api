<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\OutputInterface;
use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ConfigurableHandler extends BaseHandler
{
    /**
     * @param ParamInterface[] $params
     * @param OutputInterface[] $outputs
     */
    public function __construct(private array $params = [], private array $outputs = [])
    {
        parent::__construct();
    }

    public function params(): array
    {
        return $this->params;
    }

    public function outputs(): array
    {
        return $this->outputs;
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
