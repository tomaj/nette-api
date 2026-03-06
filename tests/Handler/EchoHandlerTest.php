<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\EchoHandler;

class EchoHandlerTest extends TestCase
{
    public function testResponse()
    {
        $handler = new EchoHandler();
        self::assertEquals('', $handler->summary());
        self::assertEquals('', $handler->description());
        self::assertFalse($handler->deprecated());
        self::assertEquals([], $handler->tags());
        
        $result = $handler->handle(['status' => 'error', 'message' => 'Hello']);
        self::assertEquals(200, $result->getCode());
        
        self::assertEquals(['status' => 'error', 'params' => ['status' => 'error', 'message' => 'Hello']], $result->getPayload());
    }
}
