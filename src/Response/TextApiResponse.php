<?php

namespace Tomaj\NetteApi\Response;

use Nette\Application\Responses\TextResponse;

class TextApiResponse extends TextResponse
{
    /**
     * @var integer
     */
    private $code;

    /**
     * Create TextApiResponse
     * This class only wrap JsonResponse from Nette and add possibility
     * to setup response code and automaticaly set content type
     *
     * @param integer $code
     * @param mixed $data
     */
    public function __construct($code, $data)
    {
        parent::__construct($data);
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
