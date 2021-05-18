<?php

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use Nette\Application\IResponse as ApplicationIResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

class XmlApiResponse implements ApplicationIResponse
{
    /**
     * @var integer
     */
    private $code;

    /**
     * @var string
     */
    private $response = null;

    /**
     * @var string|int|bool|DateTimeInterface
     */
    private $expiration;

    /**
     * Create XmlApiResponse
     * This class only wrap JsonResponse from Nette and add possibility
     * to setup response code and automaticaly set content type
     *
     * @param integer $code
     * @param string $data
     * @param string|int|bool|DateTimeInterface $expiration
     */
    public function __construct($code, $data, $expiration = false)
    {
        $this->code = $code;
        $this->response = $data;
        $this->expiration = $expiration;
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
     * {@inheritdoc}
     */
    public function send(IRequest $httpRequest, IResponse $httpResponse)
    {
        $httpResponse->setContentType('text/xml');
        $httpResponse->setExpiration($this->expiration);
        $httpResponse->setCode($this->getCode());
        $httpResponse->setHeader('Content-Length', strlen($this->response));

        echo $this->response;
    }
}
