<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

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

    public function __construct(int $code, string $data)
    {
        $this->code = $code;
        $this->response = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * {@inheritdoc}
     */
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->setContentType('text/xml');
        $httpResponse->setExpiration(null);
        $httpResponse->setHeader('Content-Length', (string) strlen($this->response));

        echo $this->response;
    }
}
