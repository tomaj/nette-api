<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use Nette\Application\IResponse;

interface ResponseInterface extends IResponse
{
    /**
     * Return api response http code
     */
    public function getCode(): int;
}
