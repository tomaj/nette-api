<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\XmlApiResponse;

final class XmlApiResponseTest extends TestCase
{
    public function testCreatingResponse(): void
    {
        $xmlResponse = new XmlApiResponse(200, '<data>hello</data>');
        $this->assertSame(200, $xmlResponse->getCode());

        $this->expectOutputString('<data>hello</data>');
        $xmlResponse->send(new Request(new UrlScript()), new Response());
    }

    public function testSendsContentLengthAndKeepsDataParameterName(): void
    {
        $xmlResponse = new XmlApiResponse(code: 200, data: '<a>ž</a>');

        $httpResponse = $this->createMock(IResponse::class);
        $httpResponse->expects($this->once())->method('setContentType')->with('text/xml');
        $httpResponse->expects($this->once())->method('setHeader')->with('Content-Length', '9');

        $this->expectOutputString('<a>ž</a>');
        $xmlResponse->send(new Request(new UrlScript()), $httpResponse);
    }
}
