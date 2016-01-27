<?php

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Response\XmlApiResponse;

class XmlApiResponseTest extends PHPUnit_Framework_TestCase
{
    public function testCreatingResponse()
    {
        $xmlResponse = new XmlApiResponse(200, '<data>hello</data>');
        $this->assertEquals(200, $xmlResponse->getCode());

        $this->expectOutputString('<data>hello</data>');
        $xmlResponse->send(new Request(new UrlScript()), new Response());
    }
}
