<?php

namespace Tomaj\NetteApi\Response;

use Nette\Application\IResponse as ApplicationIResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

class XmlApiResponse implements ApplicationIResponse
{
    /**
     * @var integer
     */
    private $code;

    /** @var null|string  */
    private $response = null;

    /**
     * Create XmlApiResponse
     * This class only wrap JsonResponse from Nette and add possibility
     * to setup response code and automaticaly set content type
     *
     * @param integer $code
     * @param mixed $data
     */
    public function __construct($code, $data)
    {
        $this->code = $code;
        $this->response = $data;
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

    public function send(IRequest $httpRequest, IResponse $httpResponse)
    {
        if (!$this->response) {
            echo 'Generate or set response first';
            return;
        }

        $httpResponse->setContentType('text/xml');
        $httpResponse->setExpiration(false);
        $httpResponse->setCode($this->getCode());

        echo $this->response;
    }
}
