<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use DateTimeInterface;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;

/**
 * @method mixed __call(string $name, array<mixed> $args)
 * @method static void __callStatic(string $name, array<mixed> $args)
 */
class XmlApiResponse implements ResponseInterface
{
    use SmartObject;

    /**
     * @param DateTimeInterface|null|false $expiration
     */
    public function __construct(private int $code, private string $data, private $expiration = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    public function getExpiration(): DateTimeInterface|false|null
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

        $httpResponse->setHeader('Content-Length', (string) strlen($this->data));

        echo $this->data;
    }
}
