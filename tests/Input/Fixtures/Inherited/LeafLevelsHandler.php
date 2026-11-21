<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Inherited;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class LeafLevelsHandler extends TypedHandler
{
    public function handleDto(LeafLevelsInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['levels' => $input->levels, 'casted' => $input->casted]);
    }
}
