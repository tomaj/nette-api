<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\EndpointIdentifier;

class EndpointIdentifierTest extends TestCase
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

    public function testSimpleUrl()
    {
        $endpoint = new EndpointIdentifier('get', 2, 'main');
        $this->assertNull($endpoint->getApiAction());
        $this->assertEquals('GET', $endpoint->getMethod());
    }
}
