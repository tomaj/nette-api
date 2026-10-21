<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\RedirectResponse;

class RedirectResponseTest extends TestCase
{
    public function testDefaultRedirect()
    {
        $url = 'https://example.com/resource';
        $apiResponse = new RedirectResponse($url);

        self::assertSame($url, $apiResponse->getUrl());
        self::assertSame(IResponse::S302_FOUND, $apiResponse->getCode());

        $httpResponse = $this->createMock(IResponse::class);
        $httpResponse->expects(self::once())
            ->method('redirect')
            ->with($url, IResponse::S302_FOUND);

        $apiResponse->send(new Request(new UrlScript()), $httpResponse);
    }

    public function testRedirectWithCustomStatusCode()
    {
        $url = '/resource';
        $statusCode = IResponse::S301_MOVED_PERMANENTLY;
        $apiResponse = new RedirectResponse($url, $statusCode);

        self::assertSame($url, $apiResponse->getUrl());
        self::assertSame($statusCode, $apiResponse->getCode());

        $httpResponse = $this->createMock(IResponse::class);
        $httpResponse->expects(self::once())
            ->method('redirect')
            ->with($url, $statusCode);

        $apiResponse->send(new Request(new UrlScript()), $httpResponse);
    }
}
