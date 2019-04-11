<?php

namespace Tomaj\NetteApi\Response;

use Nette\Application\IResponse;

interface ResponseInterface extends IResponse
{
    /**
     * Return api response http code
     *
     * @return int
     */
    public function getCode(): int;
}
