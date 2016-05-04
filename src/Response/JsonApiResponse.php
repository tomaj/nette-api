<?php

namespace Tomaj\NetteApi\Response;

use Nette\Application\Responses\JsonResponse;
use Nette;

class JsonApiResponse extends JsonResponse
{
    /**
     * @var integer
     */
    private $code;

    /**
     * @var string
     */
    private $charset;

    /**
     * Create JsonApiResponse
     * This class only wrap JsonResponse from Nette and add possibility
     * to setup response code and automaticaly set content type
     *
     * @param integer $code
     * @param mixed $data
     * @param string $contentType
     * @param string $charset
     */
    public function __construct($code, $data, $contentType = 'application/json', $charset = 'utf-8')
    {
        parent::__construct($data, $contentType);
        $this->charset = $charset;
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

    /**
     * Return encoding charset for http response
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Sends response to output.
     * @return void
     */
    public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse)
    {
        $httpResponse->setContentType($this->contentType, $this->charset);
        $httpResponse->setExpiration(false);
        echo Nette\Utils\Json::encode($this->payload);
    }
}
