<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use JsonSerializable;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;
use Nette\Utils\Json;

readonly class JsonApiResponse implements ResponseInterface
{
    use SmartObject;

    public readonly string $contentType {
        get => $this->contentType ?: 'application/json';
    }

    public readonly string $fullContentType {
        get => $this->contentType . '; charset=' . $this->charset;
    }

    public function __construct(
        public readonly int $code,
        public readonly array|JsonSerializable $payload,
        string $contentType = 'application/json',
        public readonly string $charset = 'utf-8',
        public readonly DateTimeInterface|null|false $expiration = null
    ) {
        $this->contentType = $contentType;
    }

    public function getCode(): int
    {
        return $this->code;
    }

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

    public function getExpiration(): ?DateTimeInterface
    {
        return $this->expiration;
    }

    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->setContentType($this->getContentType(), $this->getCharset());
        
        if ($this->expiration !== false) {
            $httpResponse->setExpiration($this->getExpiration()?->format('c'));
        }
        
        $result = Json::encode($this->getPayload());
        $httpResponse->setHeader('Content-Length', (string) strlen($result));
        echo $result;
    }
}
