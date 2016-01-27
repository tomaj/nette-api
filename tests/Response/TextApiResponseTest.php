<?php

namespace Tomaj\NetteApi\Test\Response;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Response\TextApiResponse;

class TextApiResponseTest extends PHPUnit_Framework_TestCase
{
    public function testCreatingResponse()
    {
        $response = new TextApiResponse(200, 'hello');
        $this->assertEquals(200, $response->getCode());
    }
}
