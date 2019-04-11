<?php

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Handlers\EchoHandler;

class EchoHandlerTest extends TestCase
{
    public function testResponse()
    {
        $defaultHandler = new EchoHandler();
        $result = $defaultHandler->handle(['status' => 'error', 'message' => 'Hello']);
        $this->assertEquals(200, $result->getCode());
        
        $this->assertEquals(['status' => 'error', 'params' => ['status' => 'error', 'message' => 'Hello']], $result->getPayload());
    }
}
