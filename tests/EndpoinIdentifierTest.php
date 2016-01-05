<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\EndpointIdentifier;

class EndpointIdentifierTest extends PHPUnit_Framework_TestCase
{
    public function testValidation()
    {
        $endpoint = new EndpointIdentifier('POST', 1, 'core', 'show');

        $this->assertEquals('POST', $endpoint->getMethod());
        $this->assertEquals(1, $endpoint->getVersion());
        $this->assertEquals('core', $endpoint->getPackage());
        $this->assertEquals('show', $endpoint->getApiAction());
        $this->assertEquals('v1/core/show', $endpoint->getUrl());
    }
}
