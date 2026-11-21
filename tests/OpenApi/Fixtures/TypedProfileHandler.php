<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Every location of a typed input: path, header, query list, body scalar, nullable enum, list and object, optional file.
 */
final class TypedProfileHandler extends TypedHandler
{
    public function handleDto(TypedProfileInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['id' => $input->id]);
    }
}
