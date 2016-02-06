<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Misc\ConsoleRequest;
use Tomaj\NetteApi\Handlers\EchoHandler;

class ConsoleRequestTest extends PHPUnit_Framework_TestCase
{
    public function testLogRequest()
    {
    	$handler = new EchoHandler();

        $request = new ConsoleRequest($handler);
        $response = $request->makeRequest("http://127.0.0.1:23523/", 'POST', ['status' => 'ok', 'message' => 'Hello']);

        $this->assertTrue($response->isError());
        $this->assertEquals('http://127.0.0.1:23523/?status=ok&message=Hello', $response->getUrl());
    }
}
