<?php

namespace Tomaj\NetteApi\Test\Response;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Url;
use Nette\Http\UrlScript;
use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Response\TextApiResponse;

class TextApiResponseTest extends PHPUnit_Framework_TestCase
{
    public function testCreatingResponse()
    {
        $apiResponse = new TextApiResponse(200, 'hello');
        $this->assertEquals(200, $apiResponse->getCode());

        $request = new Request(new UrlScript());
        $response = new Response();

        $this->expectOutputString('hello');
        $apiResponse->send($request, $response);
    }
}
