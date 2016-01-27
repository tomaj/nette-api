<?php

namespace Tomaj\NetteApi\Test\Response;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Response\XmlApiResponse;

class XmlApiResponseTest extends PHPUnit_Framework_TestCase
{
    public function testCreatingResponse()
    {
        $response = new XmlApiResponse(200, '<data>hello</data>');
        $this->assertEquals(200, $response->getCode());
    }
}
