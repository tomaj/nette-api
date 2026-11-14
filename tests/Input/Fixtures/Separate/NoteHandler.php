<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Separate;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class NoteHandler extends TypedHandler
{
    public function handleDto(NoteInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['text' => $input->text]);
    }
}
