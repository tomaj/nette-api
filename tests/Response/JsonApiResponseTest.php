<?php

namespace Tomaj\NetteApi\Test\Response;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Response\JsonApiResponse;

class JsonApiResponseTest extends PHPUnit_Framework_TestCase
{
    public function testTralala()
    {
        $response = new JsonApiResponse(200, ['asdasd' => 'asdsd']);
        $this->assertEquals(200, $response->getCode());
    }
}
