<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\ConsoleResponse;

class ConsoleResponseTest extends TestCase
{
    public function testLogRequest()
    {
        $response = new ConsoleResponse(
            'http://url.com/',
            'POST',
            ['mykey1' => 'asdsd'],
            ['mykey2' => 'wegewg'],
            ['mykey3' => 'gwegerg'],
            ['Content-Type' => 'text']
        );

        $this->assertEquals('http://url.com/', $response->getUrl());
        $this->assertEquals('POST', $response->getMethod());
        $this->assertEquals(['mykey1' => 'asdsd'], $response->getPostFields());
        $this->assertEquals(['mykey2' => 'wegewg'], $response->getGetFields());
        $this->assertEquals(['mykey3' => 'gwegerg'], $response->getCookieFields());
        $this->assertEquals(['Content-Type' => 'text'], $response->getHeaders());
        $this->assertNull($response->getResponseCode());
        $this->assertFalse($response->isError());

        $response->logRequest(202, '{"aaa": "bbb"}', 'responseheadersd', 145);
        $this->assertEquals('{"aaa": "bbb"}', $response->getResponseBody());
        $this->assertEquals('responseheadersd', $response->getResponseHeaders());
        $this->assertEquals("{\n    \"aaa\": \"bbb\"\n}", $response->getFormattedJsonBody());
        $this->assertEquals(145, $response->getResponseTime());
        $this->assertFalse($response->isError());
        $this->assertEquals(202, $response->getResponseCode());
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
