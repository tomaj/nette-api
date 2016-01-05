<?php

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Handlers\DefaultHandler;

class DefaultHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testResponse()
    {
        $defaultHandler = new DefaultHandler();
        $result = $defaultHandler->handle([]);
        $this->assertEquals(500, $result->getCode());
        $this->assertEquals('application/json; charset=utf-8', $result->getContentType());
        $this->assertEquals(['status' => 'error', 'message' => 'Unknown api endpoint'], $result->getPayload());
    }
}
