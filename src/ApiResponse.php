<?php

namespace Tomaj\NetteApi;

use Nette\Application\Responses\JsonResponse;

class ApiResponse extends JsonResponse
{
    /**
     * @var integer
     */
    private $code;

    /**
     * Create ApiResponse
     * This class only wrap JsonResponse from Nette and add possibility
     * to setup response code and automaticaly set content type
     *
     * @param integer $code
     * @param mixed $data
     * @param string $contentType
     */
    public function __construct($code, $data, $contentType = 'application/json; charset=utf-8')
    {
        parent::__construct($data, $contentType);
        $this->code = $code;
    }

    /**
     * Return api response http code
     *
     * @return integer
     */
    public function getCode()
    {
        return $this->code;
    }
}
