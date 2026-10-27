<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Response;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\SmartObject;

class RedirectResponse implements ResponseInterface
{
    use SmartObject;

    public function __construct(private string $url, private int $httpCode = IResponse::S302_FOUND)
    {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    #[\Override]
    public function getCode(): int
    {
        return $this->httpCode;
    }

    #[\Override]
    public function send(IRequest $httpRequest, IResponse $httpResponse): void
    {
        $httpResponse->redirect($this->url, $this->httpCode);
    }
}
