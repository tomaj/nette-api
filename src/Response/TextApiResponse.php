<?php

namespace Tomaj\NetteApi\Response;

use Nette\Application\Responses\TextResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

class TextApiResponse extends TextResponse
{
    /**
     * @var integer
     */
    private $code;

    private $data;

    private $contentType;

    private $charset;

    private $expiration;

    /**
     * Create TextApiResponse
     *
     * @param integer $code
     * @param string $data
     * @param string $contentType
     * @param string $charset
     * @param mixed $expiration
     */
    public function __construct($code, $data, $contentType = 'text/plain', $charset = 'utf-8', $expiration = false)
    {
        parent::__construct($data);
        $this->code = $code;
        $this->data = $data;
        $this->contentType = $contentType;
        $this->charset = $charset;
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

    public function send(IRequest $httpRequest, IResponse $httpResponse)
    {
        $httpResponse->setContentType($this->contentType, $this->charset);
        $httpResponse->setExpiration($this->expiration);
        $httpResponse->setHeader('Content-Length', strlen($this->data));
        echo $this->data;
    }
}
