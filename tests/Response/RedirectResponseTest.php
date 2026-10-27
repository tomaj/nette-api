<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\RedirectResponse;

final class RedirectResponseTest extends TestCase
{
    public function testDefaultRedirect(): void
    {
        $url = 'https://example.com/resource';
        $apiResponse = new RedirectResponse($url);

        $this->assertSame($url, $apiResponse->getUrl());
        $this->assertSame(IResponse::S302_FOUND, $apiResponse->getCode());

        $httpResponse = $this->createMock(IResponse::class);
        $httpResponse->expects($this->once())
            ->method('redirect')
            ->with($url, IResponse::S302_FOUND);

        $apiResponse->send(new Request(new UrlScript()), $httpResponse);
    }

    public function testRedirectWithCustomStatusCode(): void
    {
        $url = '/resource';
        $statusCode = IResponse::S301_MOVED_PERMANENTLY;
        $apiResponse = new RedirectResponse($url, $statusCode);

        $this->assertSame($url, $apiResponse->getUrl());
        $this->assertSame($statusCode, $apiResponse->getCode());

        $httpResponse = $this->createMock(IResponse::class);
        $httpResponse->expects($this->once())
            ->method('redirect')
            ->with($url, $statusCode);

        $apiResponse->send(new Request(new UrlScript()), $httpResponse);
    }
}
