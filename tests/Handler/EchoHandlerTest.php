<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\EchoHandler;

final class EchoHandlerTest extends TestCase
{
    public function testResponse(): void
    {
        $handler = new EchoHandler();
        $this->assertSame('', $handler->summary());
        $this->assertSame('', $handler->description());
        $this->assertFalse($handler->deprecated());
        $this->assertEquals([], $handler->tags());
        
        $result = $handler->handle(['status' => 'error', 'message' => 'Hello']);
        $this->assertSame(200, $result->getCode());
        
        $this->assertEquals(['status' => 'error', 'params' => ['status' => 'error', 'message' => 'Hello']], $result->getPayload());
    }

    public function testParams(): void
    {
        $params = new EchoHandler()->params();
        $this->assertCount(2, $params);
        $this->assertSame(['ok' => 'ok', 'error' => 'error'], $params[0]->getAvailableValues());
    }
}
