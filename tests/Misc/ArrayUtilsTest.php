<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Misc\ArrayUtils;
use Tomaj\NetteApi\ValidationResult\ValidationStatus;

final class ArrayUtilsTest extends TestCase
{
    private array $endpoints;

    protected function setUp(): void
    {
        $this->endpoints = [
            new EndpointIdentifier('GET', '1', 'users', 'list'),
            new EndpointIdentifier('POST', '1', 'users', 'create'),
            new EndpointIdentifier('GET', '2', 'posts', 'list'),
            new EndpointIdentifier('DELETE', '1', 'users', 'delete'),
        ];
    }

    #[Test]
    public function findApiEndpointReturnsFirstMatch(): void
    {
        $result = ArrayUtils::findApiEndpoint(
            $this->endpoints,
            fn($endpoint) => $endpoint->getMethod() === 'GET'
        );

        $this->assertInstanceOf(EndpointIdentifier::class, $result);
        $this->assertSame('GET', $result->getMethod());
        $this->assertSame('users', $result->getPackage());
    }

    #[Test]
    public function findApiEndpointReturnsNullWhenNotFound(): void
    {
        $result = ArrayUtils::findApiEndpoint(
            $this->endpoints,
            fn($endpoint) => $endpoint->getMethod() === 'PATCH'
        );

        $this->assertNull($result);
    }

    #[Test]
    public function findApiEndpointKeyReturnsCorrectKey(): void
    {
        $result = ArrayUtils::findApiEndpointKey(
            $this->endpoints,
            fn($endpoint) => $endpoint->getVersion() === '2'
        );

        $this->assertSame(2, $result);
    }

    #[Test]
    public function hasApiEndpointReturnsTrueWhenExists(): void
    {
        $result = ArrayUtils::hasApiEndpoint(
            $this->endpoints,
            fn($endpoint) => $endpoint->getPackage() === 'posts'
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function hasApiEndpointReturnsFalseWhenNotExists(): void
    {
        $result = ArrayUtils::hasApiEndpoint(
            $this->endpoints,
            fn($endpoint) => $endpoint->getPackage() === 'comments'
        );

        $this->assertFalse($result);
    }

    #[Test]
    public function allApiEndpointsMatchReturnsTrueWhenAllMatch(): void
    {
        $result = ArrayUtils::allApiEndpointsMatch(
            $this->endpoints,
            fn($endpoint) => in_array($endpoint->getMethod(), ['GET', 'POST', 'DELETE'], true)
        );

        $this->assertTrue($result);
    }

    #[Test]
    public function allApiEndpointsMatchReturnsFalseWhenNotAllMatch(): void
    {
        $result = ArrayUtils::allApiEndpointsMatch(
            $this->endpoints,
            fn($endpoint) => $endpoint->getMethod() === 'GET'
        );

        $this->assertFalse($result);
    }

    #[Test]
    #[DataProvider('filterByMethodProvider')]
    public function filterByMethodReturnsCorrectEndpoints(string $method, int $expectedCount): void
    {
        $result = ArrayUtils::filterByMethod($this->endpoints, $method);
        
        $this->assertCount($expectedCount, $result);
        
        foreach ($result as $endpoint) {
            $this->assertSame(strtoupper($method), $endpoint->getMethod());
        }
    }

    public static function filterByMethodProvider(): array
    {
        return [
            'GET endpoints' => ['GET', 2],
            'POST endpoints' => ['POST', 1],
            'DELETE endpoints' => ['DELETE', 1],
            'PUT endpoints' => ['PUT', 0],
        ];
    }

    #[Test]
    public function groupByVersionGroupsCorrectly(): void
    {
        $result = ArrayUtils::groupByVersion($this->endpoints);

        $this->assertArrayHasKey('1', $result);
        $this->assertArrayHasKey('2', $result);
        $this->assertCount(3, $result['1']);
        $this->assertCount(1, $result['2']);
    }

    #[Test]
    public function getFirstEndpointReturnsFirstElement(): void
    {
        $result = ArrayUtils::getFirstEndpoint($this->endpoints);

        $this->assertInstanceOf(EndpointIdentifier::class, $result);
        $this->assertSame('GET', $result->getMethod());
        $this->assertSame('users', $result->getPackage());
    }

    #[Test]
    public function getLastEndpointReturnsLastElement(): void
    {
        $result = ArrayUtils::getLastEndpoint($this->endpoints);

        $this->assertInstanceOf(EndpointIdentifier::class, $result);
        $this->assertSame('DELETE', $result->getMethod());
        $this->assertSame('users', $result->getPackage());
    }

    #[Test]
    public function validateEndpointsReturnsOkForValidEndpoints(): void
    {
        $result = ArrayUtils::validateEndpoints($this->endpoints);

        $this->assertTrue($result->isOk);
        $this->assertSame(ValidationStatus::OK, $result->status);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function validateEndpointsReturnsErrorsForInvalidEndpoints(): void
    {
        $invalidEndpoints = [
            $this->endpoints[0],
            'invalid',
            new EndpointIdentifier('INVALID', '1', 'test', null)
        ];

        $result = ArrayUtils::validateEndpoints($invalidEndpoints);

        $this->assertFalse($result->isOk);
        $this->assertSame(ValidationStatus::ERROR, $result->status);
        $this->assertNotEmpty($result->errors);
    }
}