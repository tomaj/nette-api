<?php

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Response\JsonApiResponse;

class JsonApiResponseTest extends PHPUnit_Framework_TestCase
{
    public function testCreatingResponse()
    {
        $jsonResponse = new JsonApiResponse(200, ['asdasd' => 'asdsd']);
        $this->assertEquals(200, $jsonResponse->getCode());

        $this->expectOutputString('{"asdasd":"asdsd"}');
        $jsonResponse->send(new Request(new UrlScript()), new Response());
    }
}
