<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\EndpointIdentifier;

class EndpointIdentifierTest extends TestCase
{
    public function testValidation()
    {
        $endpoint = new EndpointIdentifier('POST', '1', 'core', 'show');

        $this->assertSame('1', $endpoint->getVersion());
        $this->assertEquals('POST', $endpoint->getMethod());
        $this->assertEquals('core', $endpoint->getPackage());
        $this->assertEquals('show', $endpoint->getApiAction());
        $this->assertEquals('v1/core/show', $endpoint->getUrl());

        $endpoint = new EndpointIdentifier('POST', '1.1', 'core', 'show');
        $this->assertEquals('v1.1/core/show', $endpoint->getUrl());

        $endpoint = new EndpointIdentifier('POST', '1.1.2', 'core', 'show');
        $this->assertEquals('v1.1.2/core/show', $endpoint->getUrl());
    }

    public function testSimpleUrl()
    {
        $endpoint = new EndpointIdentifier('get', '2', 'main', '');
        $this->assertNull($endpoint->getApiAction());
        $this->assertEquals('GET', $endpoint->getMethod());
    }

    public function testSupportedVersions()
    {
        $endpoint = new EndpointIdentifier('GET', '0', 'core', 'show');
        $this->assertEquals('v0/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1', 'core', 'show');
        $this->assertEquals('v1/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.0', 'core', 'show');
        $this->assertEquals('v1.0/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.1', 'core', 'show');
        $this->assertEquals('v1.1/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.33', 'core', 'show');
        $this->assertEquals('v1.33/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.33-dev', 'core', 'show');
        $this->assertEquals('v1.33-dev/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '0.33.43', 'core', 'show');
        $this->assertEquals('v0.33.43/core/show', $endpoint->getUrl());
    }

    public function testFailVersion()
    {
        $this->expectException(InvalidArgumentException::class);
        new EndpointIdentifier('GET', '1.0/dev', 'core', 'show');
    }
}
