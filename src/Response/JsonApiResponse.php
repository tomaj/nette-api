<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use JsonSerializable;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;
use Nette\Utils\Json;

class JsonApiResponse implements ResponseInterface
{
    use SmartObject;

    private string $contentType;

    /**
     * @param array<mixed>|JsonSerializable $payload
     * @param DateTimeInterface|null|false $expiration
     */
    public function __construct(private int $code, private $payload, string $contentType = 'application/json', private string $charset = 'utf-8', private $expiration = null)
    {
        $this->contentType = $contentType ?: 'application/json';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return array<mixed>|JsonSerializable
     */
    public function getPayload(): array|JsonSerializable
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

    public function getExpiration(): DateTimeInterface|false|null
    {
        return $this->expiration;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->setContentType($this->getContentType(), $this->getCharset());
        if ($this->expiration !== false) {
            $httpResponse->setExpiration($this->getExpiration() ? $this->getExpiration()->format('c') : null);
        }

        $result = Json::encode($this->getPayload());
        $httpResponse->setHeader('Content-Length', (string) strlen($result));
        echo $result;
    }
}
