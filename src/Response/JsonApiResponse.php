<?php

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use Nette;
use Nette\Application\Responses\JsonResponse;

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
     * @var string|int|bool|DateTimeInterface
     */
    private $expiration;

    /**
     * Create JsonApiResponse
     * This class only wrap JsonResponse from Nette and add possibility
     * to setup response code and automaticaly set content type
     *
     * @param integer $code
     * @param mixed $data
     * @param string $contentType
     * @param string $charset
     * @param string|int|bool|DateTimeInterface $expiration
     */
    public function __construct($code, $data, $contentType = 'application/json', $charset = 'utf-8', $expiration = false)
    {
        parent::__construct($data, $contentType);
        $this->charset = $charset;
        $this->code = $code;
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
        $httpResponse->setContentType($this->getContentType(), $this->charset);
        $httpResponse->setExpiration($this->expiration);
        $result = Nette\Utils\Json::encode($this->getPayload());
        $httpResponse->setHeader('Content-Length', strlen($result));
        echo $result;
    }
}
