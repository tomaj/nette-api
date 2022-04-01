<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use Nette\Http\IResponse;
use Nette\Http\IRequest;
use Nette\SmartObject;

class RedirectResponse implements ResponseInterface
{
    use SmartObject;

    /** @var string */
    private $url;

    /** @var int */
    private $httpCode;

    public function __construct(string $url, int $httpCode = IResponse::S302_FOUND)
    {
        $this->url = $url;
        $this->httpCode = $httpCode;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCode(): int
    {
        return $this->httpCode;
    }

    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->redirect($this->url, $this->httpCode);
    }
}
