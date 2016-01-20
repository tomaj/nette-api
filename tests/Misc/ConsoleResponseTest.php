<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Misc\ConsoleResponse;

class ConsoleRequestTest extends PHPUnit_Framework_TestCase
{
    public function testLogRequest()
    {
        $response = new ConsoleResponse(
            'http://url.com/',
            'POST',
            ['mykey1' => 'asdsd'],
            ['mykey2' => 'wegewg'],
            ['Content-Type' => 'text']
        );

        $this->assertEquals('http://url.com/', $response->getUrl());
        $this->assertEquals('POST', $response->getMethod());
        $this->assertEquals(['mykey1' => 'asdsd'], $response->getPostFields());
        $this->assertEquals(['mykey2' => 'wegewg'], $response->getGetFields());
        $this->assertEquals(['Content-Type' => 'text'], $response->getHeaders());
        $this->assertFalse($response->isError());

        $response->logRequest(202, 'asdsafsdgdsg', 'responseheadersd', 145);
        $this->assertEquals('asdsafsdgdsg', $response->getResponseBody());
        $this->assertEquals('responseheadersd', $response->getResponseHeaders());
        $this->assertEquals('asdsafsdgdsg', $response->getFormattedJsonBody());
        $this->assertEquals(145, $response->getResponseTime());
        $this->assertFalse($response->isError());
    }

    public function testLogErrorRequest()
    {
        $response = new ConsoleResponse(
            'http://url.com/',
            'POST',
            ['mykey1' => 'asdsd'],
            ['mykey2' => 'wegewg'],
            ['Content-Type' => 'text']
        );

        $response->logError(503, 'err message', 21);
        $this->assertEquals(503, $response->getErrorNumber());
        $this->assertEquals('err message', $response->getErrorMessage());
        $this->assertEquals(21, $response->getResponseTime());
        $this->assertTrue($response->isError());
    }
}
