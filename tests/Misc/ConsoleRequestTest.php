<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\ConsoleRequest;
use Tomaj\NetteApi\Handlers\EchoHandler;

class ConsoleRequestTest extends TestCase
{
    public function testLogRequest()
    {
    	$handler = new EchoHandler();

        $request = new ConsoleRequest($handler);
        $response = $request->makeRequest("http://127.0.0.1:23523/", 'POST', ['status' => 'ok', 'message' => 'Hello']);

        self::assertTrue($response->isError());
        self::assertEquals('http://127.0.0.1:23523/?status=ok&message=Hello', $response->getUrl());
    }
}
