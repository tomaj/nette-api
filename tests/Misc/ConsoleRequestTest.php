<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\ConsoleRequest;
use Tomaj\NetteApi\Handlers\EchoHandler;

final class ConsoleRequestTest extends TestCase
{
    public function testLogRequest(): void
    {
    	$handler = new EchoHandler();

        $request = new ConsoleRequest($handler);
        $response = $request->makeRequest("http://127.0.0.1:23523/", 'POST', ['status' => 'ok', 'message' => 'Hello']);

        $this->assertTrue($response->isError());
        $this->assertSame('http://127.0.0.1:23523/?status=ok&message=Hello', $response->getUrl());
    }

    public function testMultiValueKeysAreMappedToTheirParam(): void
    {
        $request = new ConsoleRequest(new EchoHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['status' => 'ok', 'message___1' => 'Hello']);

        $this->assertSame('http://127.0.0.1:23523/?status=ok&message=Hello', $response->getUrl());
    }
}
