<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\XmlApiResponse;

class XmlApiResponseTest extends TestCase
{
    public function testCreatingResponse()
    {
        $xmlResponse = new XmlApiResponse(200, '<data>hello</data>');
        $this->assertEquals(200, $xmlResponse->getCode());

        $this->expectOutputString('<data>hello</data>');
        $xmlResponse->send(new Request(new UrlScript()), new Response());
    }
}
