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

    /** @var array */
    private $payload;

    /** @var string */
    private $contentType;

    /** @var string */
    private $charset;

    /** @var DateTimeInterface|null */
    private $expiration;

    public function __construct(int $code, array $payload, string $contentType = 'application/json', string $charset = 'utf-8', ?DateTimeInterface $expiration = null)
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

    public function getPayload(): array
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

    public function getExpiration(): ?DateTimeInterface
    {
        return $this->expiration;
    }

    /**
     * {@inheritdoc}
     */
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->setContentType($this->getContentType(), $this->getCharset());
        $httpResponse->setExpiration($this->getExpiration() ? $this->getExpiration()->format('c') : null);
        $result = Json::encode($this->getPayload());
        $httpResponse->setHeader('Content-Length', (string) strlen($result));
        echo $result;
    }
}
