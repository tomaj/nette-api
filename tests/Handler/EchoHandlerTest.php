<?php

namespace Tomaj\NetteApi\Test\Handler;

use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\Url;
use PHPUnit_Framework_TestCase;

class EchoHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testResponse()
    {
        $defaultHandler = new EchoHandler();
        $result = $defaultHandler->handle(['status' => 'error', 'message' => 'Hello']);
        $this->assertEquals(200, $result->getCode());
        
        $this->assertEquals(['status' => 'error', 'params' => ['status' => 'error', 'message' => 'Hello']], $result->getPayload());
    }
}
