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

        self::assertSame('1', $endpoint->getVersion());
        self::assertEquals('POST', $endpoint->getMethod());
        self::assertEquals('core', $endpoint->getPackage());
        self::assertEquals('show', $endpoint->getApiAction());
        self::assertEquals('v1/core/show', $endpoint->getUrl());

        $endpoint = new EndpointIdentifier('POST', '1.1', 'core', 'show');
        self::assertEquals('v1.1/core/show', $endpoint->getUrl());

        $endpoint = new EndpointIdentifier('POST', '1.1.2', 'core', 'show');
        self::assertEquals('v1.1.2/core/show', $endpoint->getUrl());
    }

    public function testSimpleUrl()
    {
        $endpoint = new EndpointIdentifier('get', '2', 'main', '');
        self::assertNull($endpoint->getApiAction());
        self::assertEquals('GET', $endpoint->getMethod());
    }

    public function testSupportedVersions()
    {
        $endpoint = new EndpointIdentifier('GET', '0', 'core', 'show');
        self::assertEquals('v0/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1', 'core', 'show');
        self::assertEquals('v1/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.0', 'core', 'show');
        self::assertEquals('v1.0/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.1', 'core', 'show');
        self::assertEquals('v1.1/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.33', 'core', 'show');
        self::assertEquals('v1.33/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '1.33-dev', 'core', 'show');
        self::assertEquals('v1.33-dev/core/show', $endpoint->getUrl());
        $endpoint = new EndpointIdentifier('GET', '0.33.43', 'core', 'show');
        self::assertEquals('v0.33.43/core/show', $endpoint->getUrl());
    }

    public function testFailVersion()
    {
        $this->expectException(InvalidArgumentException::class);
        new EndpointIdentifier('GET', '1.0/dev', 'core', 'show');
    }
}
