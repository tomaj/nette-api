<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\InvalidStateException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\JsonApiResponse;

class TestHandlerTest extends TestCase
{
    public function testParentConstructCall()
    {
        $handler = new TestHandler();
        $this->assertEquals('Test handler', $handler->summary());
        $this->assertEquals('This API handler is for test purpose and it is marked as deprecated', $handler->description());
        $this->assertTrue($handler->deprecated());
        $this->assertEquals(['test'], $handler->tags());

        /** @var JsonApiResponse $result */
        $result = $handler->handle([]);
        $this->assertEquals(200, $result->getCode());
        $this->assertEquals(['hello' => 'world'], $result->getPayload());

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage("Fractal manager isn't initialized. Did you call parent::__construct() in your handler constructor?");
        $handler->handle(['use_fractal' => true]);
    }
}
