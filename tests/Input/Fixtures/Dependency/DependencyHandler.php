<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class DependencyHandler extends TypedHandler
{
    public function handleDto(DependencyInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['name' => $input->name]);
    }
}
