<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\OpenApi\EndpointFilter;

class EndpointFilterTest extends TestCase
{
    /**
     * @return array<string, array{array<string|int, mixed>, string, string, bool}>
     */
    public static function provideEndpoints(): array
    {
        return [
            'empty allows all' => [[], 'users', 'DELETE', true],
            'path only' => [['users/list'], 'users/list', 'GET', true],
            'path only not matching' => [['users/list'], 'orders', 'GET', false],
            'wildcard path' => [['users/*'], 'users/detail', 'POST', true],
            'path is case insensitive' => [['Users/List'], 'users/list', 'GET', true],
            'single method' => [['users' => 'post'], 'users', 'POST', true],
            'single method not matching' => [['users' => 'post'], 'users', 'GET', false],
            'list of methods' => [['users/*' => ['GET', 'delete']], 'users/detail', 'DELETE', true],
            'list of methods not matching' => [['users/*' => ['get']], 'users/detail', 'PUT', false],
            'empty methods allow all' => [['users' => []], 'users', 'PATCH', true],
            'second rule matches' => [['orders' => 'get', 'users' => 'get'], 'users', 'GET', true],
        ];
    }

    /**
     * @dataProvider provideEndpoints
     * @param array<string|int, mixed> $allowedEndpoints
     */
    public function testIsAllowed(array $allowedEndpoints, string $path, string $method, bool $expected): void
    {
        self::assertSame($expected, (new EndpointFilter($allowedEndpoints))->isAllowed($path, $method));
    }
}
