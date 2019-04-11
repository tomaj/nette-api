<?php

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;
use Nette\Utils\Json;

class JsonApiResponse implements ResponseInterface
{
    use SmartObject;

    /** @var integer */
    private $code;

    /** @var mixed */
    private $payload;

    /** @var string */
    private $contentType;

    /** @var string */
    private $charset;

    /** @var bool|DateTimeInterface|int|string */
    private $expiration;

    /**
     * This class is only copy of JsonResponse from Nette with added possibility
     * to setup response code, content type, charset and expiration
     *
     * @param integer $code
     * @param mixed $data
     * @param string $contentType
     * @param string $charset
     * @param bool|DateTimeInterface|int|string $expiration
     */
    public function __construct($code, $payload, $contentType = 'application/json', $charset = 'utf-8', $expiration = false)
    {
        $this->code = $code;
        $this->payload = $payload;
        $this->contentType = $contentType ?: 'application/json';
        $this->charset = $charset;
        $this->expiration = $expiration;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @return bool|DateTimeInterface|int|string
     */
    public function getExpiration()
    {
        return $this->expiration;
    }

    /**
     * {@inheritdoc}
     */
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->setContentType($this->getContentType(), $this->getCharset());
        $httpResponse->setExpiration($this->getExpiration());
        $result = Json::encode($this->getPayload());
        $httpResponse->setHeader('Content-Length', strlen($result));
        echo $result;
    }
}
