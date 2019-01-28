<?php

namespace Tomaj\NetteApi\Output;

use Nette\Application\IResponse;

interface OutputInterface
{
    /**
     * @param IResponse $response
     * @return bool
     */
    public function validate($response);
}
