<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\EndpointIdentifier;

final class EndpointIdentifierTest extends TestCase
{
    public function testValidation(): void
    {
        $endpoint = new EndpointIdentifier('POST', '1', 'core', 'show');

        $this->assertSame('1', $endpoint->getVersion());
        $this->assertSame('POST', $endpoint->getMethod());
        $this->assertSame('core', $endpoint->getPackage());
        $this->assertSame('show', $endpoint->getApiAction());
        $this->assertSame('v1/core/show', $endpoint->getUrl());

        $endpoint = new EndpointIdentifier('POST', '1.1', 'core', 'show');
        $this->assertSame('v1.1/core/show', $endpoint->getUrl());

        $endpoint = new EndpointIdentifier('POST', '1.1.2', 'core', 'show');
        $this->assertSame('v1.1.2/core/show', $endpoint->getUrl());
    }

    public function testSimpleUrl(): void
    {
        $endpoint = new EndpointIdentifier('get', '2', 'main', '');
        $this->assertNull($endpoint->getApiAction());
        $this->assertSame('GET', $endpoint->getMethod());
    }

    public function testSupportedVersions(): void
    {
        $endpoint = new EndpointIdentifier('GET', '0', 'core', 'show');
        $this->assertSame('v0/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1', 'core', 'show');
        $this->assertSame('v1/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.0', 'core', 'show');
        $this->assertSame('v1.0/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.1', 'core', 'show');
        $this->assertSame('v1.1/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.33', 'core', 'show');
        $this->assertSame('v1.33/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.33-dev', 'core', 'show');
        $this->assertSame('v1.33-dev/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '0.33.43', 'core', 'show');
        $this->assertSame('v0.33.43/core/show', $endpoint->getUrl());
    }

    public function testFailVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EndpointIdentifier('GET', '1.0/dev', 'core', 'show');
    }
}
