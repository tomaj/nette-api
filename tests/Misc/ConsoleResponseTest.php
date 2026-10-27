<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\ConsoleResponse;

final class ConsoleResponseTest extends TestCase
{
    public function testLogRequest(): void
    {
        $response = new ConsoleResponse(
            'http://url.com/',
            'POST',
            ['mykey1' => 'asdsd'],
            ['mykey2' => 'wegewg'],
            ['mykey3' => 'gwegerg'],
            ['Content-Type' => 'text']
        );

        $this->assertSame('http://url.com/', $response->getUrl());
        $this->assertSame('POST', $response->getMethod());
        $this->assertEquals(['mykey1' => 'asdsd'], $response->getPostFields());
        $this->assertEquals(['mykey2' => 'wegewg'], $response->getGetFields());
        $this->assertEquals(['mykey3' => 'gwegerg'], $response->getCookieFields());
        $this->assertEquals(['Content-Type' => 'text'], $response->getHeaders());
        $this->assertNull($response->getResponseCode());
        $this->assertFalse($response->isError());

        $response->logRequest(202, '{"aaa": "bbb"}', 'responseheadersd', 145);
        $this->assertSame('{"aaa": "bbb"}', $response->getResponseBody());
        $this->assertSame('responseheadersd', $response->getResponseHeaders());
        $this->assertSame("{\n    \"aaa\": \"bbb\"\n}", $response->getFormattedJsonBody());
        $this->assertSame(145, $response->getResponseTime());
        $this->assertFalse($response->isError());
        $this->assertSame(202, $response->getResponseCode());
    }

    public function testLogErrorRequest(): void
    {
        $response = new ConsoleResponse(
            'http://url.com/',
            'POST',
            ['mykey1' => 'asdsd'],
            ['mykey2' => 'wegewg'],
            ['Content-Type' => 'text']
        );

        $response->logError(503, 'err message', 21);
        $this->assertSame(503, $response->getErrorNumber());
        $this->assertSame('err message', $response->getErrorMessage());
        $this->assertSame(21, $response->getResponseTime());
        $this->assertTrue($response->isError());
    }
}
