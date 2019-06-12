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
        $this->assertEquals('', $handler->summary());
        $this->assertEquals('', $handler->description());
        $this->assertFalse($handler->deprecated());
        $this->assertEquals([], $handler->tags());
        
        $result = $handler->handle(['status' => 'error', 'message' => 'Hello']);
        $this->assertEquals(200, $result->getCode());
        
        $this->assertEquals(['status' => 'error', 'params' => ['status' => 'error', 'message' => 'Hello']], $result->getPayload());
    }
}
