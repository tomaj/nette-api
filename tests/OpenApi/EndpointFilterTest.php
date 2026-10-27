<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\OpenApi\EndpointFilter;

final class EndpointFilterTest extends TestCase
{
    /**
     * @return \Iterator<string, array{array<(int | string), mixed>, string, string, bool}>
     */
    public static function provideEndpoints(): \Iterator
    {
        yield 'empty allows all' => [[], 'users', 'DELETE', true];
        yield 'path only' => [['users/list'], 'users/list', 'GET', true];
        yield 'path only not matching' => [['users/list'], 'orders', 'GET', false];
        yield 'wildcard path' => [['users/*'], 'users/detail', 'POST', true];
        yield 'path is case insensitive' => [['Users/List'], 'users/list', 'GET', true];
        yield 'single method' => [['users' => 'post'], 'users', 'POST', true];
        yield 'single method not matching' => [['users' => 'post'], 'users', 'GET', false];
        yield 'list of methods' => [['users/*' => ['GET', 'delete']], 'users/detail', 'DELETE', true];
        yield 'list of methods not matching' => [['users/*' => ['get']], 'users/detail', 'PUT', false];
        yield 'empty methods allow all' => [['users' => []], 'users', 'PATCH', true];
        yield 'second rule matches' => [['orders' => 'get', 'users' => 'get'], 'users', 'GET', true];
    }

    /**
     * @param array<string|int, mixed> $allowedEndpoints
     */
    #[DataProvider('provideEndpoints')]
    public function testIsAllowed(array $allowedEndpoints, string $path, string $method, bool $expected): void
    {
        $this->assertSame($expected, new EndpointFilter($allowedEndpoints)->isAllowed($path, $method));
    }
}
