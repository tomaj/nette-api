<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;

class XmlApiResponse implements ResponseInterface
{
    use SmartObject;

    /** @var int */
    private $code;

    /** @var string */
    private $response = null;

    /** @var DateTimeInterface|null|false */
    private $expiration;

    /**
     * @param DateTimeInterface|null|false $expiration
     */
    public function __construct(int $code, string $data, $expiration = null)
    {
        $this->code = $code;
        $this->response = $data;
        $this->expiration = $expiration;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): int
    {
        return $this->code;
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
        $httpResponse->setContentType('text/xml');
        if ($this->expiration !== false) {
            $httpResponse->setExpiration($this->getExpiration() ? $this->getExpiration()->format('c') : null);
        }
        $httpResponse->setHeader('Content-Length', (string) strlen($this->response));

        echo $this->response;
    }
}
