<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Tomaj\NetteApi\Response\JsonApiResponse;
use Throwable;

interface ErrorHandlerInterface
{
    public function handle(Throwable $error): JsonApiResponse;
}
