<?php

namespace Tomaj\NetteApi;

use Nette\Application\Responses\JsonResponse;

class ApiResponse extends JsonResponse
{
    private $code;

    public function __construct($code, $data)
    {
        parent::__construct($data, 'application/json; charset=utf-8');
        $this->code = $code;
    }

    public function getCode()
    {
        return $this->code;
    }
}
