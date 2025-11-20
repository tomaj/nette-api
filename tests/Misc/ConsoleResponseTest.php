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

        self::assertEquals('http://url.com/', $response->getUrl());
        self::assertEquals('POST', $response->getMethod());
        self::assertEquals(['mykey1' => 'asdsd'], $response->getPostFields());
        self::assertEquals(['mykey2' => 'wegewg'], $response->getGetFields());
        self::assertEquals(['mykey3' => 'gwegerg'], $response->getCookieFields());
        self::assertEquals(['Content-Type' => 'text'], $response->getHeaders());
        self::assertNull($response->getResponseCode());
        self::assertFalse($response->isError());

        $response->logRequest(202, '{"aaa": "bbb"}', 'responseheadersd', 145);
        self::assertEquals('{"aaa": "bbb"}', $response->getResponseBody());
        self::assertEquals('responseheadersd', $response->getResponseHeaders());
        self::assertEquals("{\n    \"aaa\": \"bbb\"\n}", $response->getFormattedJsonBody());
        self::assertEquals(145, $response->getResponseTime());
        self::assertFalse($response->isError());
        self::assertEquals(202, $response->getResponseCode());
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
        self::assertEquals(503, $response->getErrorNumber());
        self::assertEquals('err message', $response->getErrorMessage());
        self::assertEquals(21, $response->getResponseTime());
        self::assertTrue($response->isError());
    }
}
