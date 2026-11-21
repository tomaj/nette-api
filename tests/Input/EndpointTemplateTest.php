<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\EndpointTemplate;

final class EndpointTemplateTest extends TestCase
{
    public function testMatch(): void
    {
        $this->assertSame(['id' => '42'], EndpointTemplate::match('v1/users/{id}/detail', 'v1/users/42/detail'));
        $this->assertNull(EndpointTemplate::match('v1/users/{id}/detail', 'v1/users/42'));
        $this->assertNull(EndpointTemplate::match('v1/users/{id}', 'v1/orders/42'));
        $this->assertSame(['id'], EndpointTemplate::placeholders('v1/users/{id}/detail'));
        $this->assertSame([], EndpointTemplate::placeholders('v1/users/x{id}'));
        $this->assertSame([], EndpointTemplate::placeholders('v1/users/{id}x'));
        $this->assertNull(EndpointTemplate::match('v1/x{id}', 'v1/99'));
        $this->assertNull(EndpointTemplate::match('v1/{id}x', 'v1/99'));
        $this->assertNull(EndpointTemplate::match('v1/{id}/detail', 'v1/1/other'));
        $this->assertFalse(EndpointTemplate::isComposite("{id}/ detail"));
        $this->assertFalse(EndpointTemplate::overlaps('v1/x{id}', 'v1/x{name}'));
        $this->assertFalse(EndpointTemplate::overlaps('v1/{id}x', 'v1/{name}x'));
        $this->assertSame(['id' => 'x42'], EndpointTemplate::match('v1/users/{id}', 'v1/users/x42'));
        $this->assertNull(EndpointTemplate::match('v1/users/{id}/detail', 'prefix/v1/users/42/detail'));
        $this->assertTrue(EndpointTemplate::isComposite('{id}/detail'));
        $this->assertFalse(EndpointTemplate::isComposite('users/detail'));
        $this->assertFalse(EndpointTemplate::isComposite('{id-1}/detail'));
        $this->assertFalse(EndpointTemplate::isComposite('{id}/detail '));
    }

    public function testThePathIsComparedAsTheRouterDecodedIt(): void
    {
        $this->assertSame(['id' => 'a b'], EndpointTemplate::match('users/{id}/detail', 'users/a b/detail'));
        $this->assertSame(['id' => '%41'], EndpointTemplate::match('users/{id}', 'users/%41'), 'not decoded twice');
        $this->assertNull(EndpointTemplate::match('users/{id}', 'us%65rs/1'));
    }

    public function testFillEncodesTheValuesAndReturnsTheUnusedOnes(): void
    {
        $this->assertSame(['users/a%20b/detail', ['q' => 1]], EndpointTemplate::fill('users/{id}/detail', ['id' => 'a b', 'q' => 1]));
        $this->assertSame(['users/{id}', ['id' => ['x']]], EndpointTemplate::fill('users/{id}', ['id' => ['x']]));
        $this->assertSame(['users/7/0', []], EndpointTemplate::fill('users/{id}/{page}', ['id' => 7, 'page' => 0]));
        $this->assertSame(['users/{id}', ['id' => null]], EndpointTemplate::fill('users/{id}', ['id' => null]));
    }

    public function testFillRejectsAValueThatLeadsElsewhere(): void
    {
        foreach (['', '.', '..', 'a/b'] as $value) {
            try {
                EndpointTemplate::fill('users/{id}', ['id' => $value]);
                $this->fail($value);
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame(sprintf("Path value 'id' cannot be '%s'. Use a non-empty value without a slash that is not '.' or '..'.", $value), $exception->getMessage());
            }
        }
        $this->assertSame(['users/..a', []], EndpointTemplate::fill('users/{id}', ['id' => '..a']));
    }

    public function testAssertNamesTheMismatch(): void
    {
        EndpointTemplate::assert('v1/users/{id}/detail', ['id'], 'UserDetailHandler');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UserDetailHandler');
        EndpointTemplate::assert('v1/users/{id}/detail', [], 'UserDetailHandler');
    }

    public function testAssertRejectsRepeatedAndMalformedPlaceholders(): void
    {
        try {
            EndpointTemplate::assert('v1/users/{id}/orders/{id}', ['id'], 'Handler');
            $this->fail('duplicate');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('repeats {id}', $exception->getMessage());
        }
        $this->expectException(\LogicException::class);
        EndpointTemplate::assert('v1/users/{id-1}', [], 'Handler');
    }

    public function testOverlap(): void
    {
        $this->assertTrue(EndpointTemplate::overlaps('v1/users/{id}', 'v1/users/{slug}'));
        $this->assertFalse(EndpointTemplate::overlaps('v1/users/{id}', 'v1/orders/{id}'));
        $this->assertFalse(EndpointTemplate::overlaps('v1/users/{id}', 'v1/users/{id}/detail'));
        $this->assertFalse(EndpointTemplate::overlaps('v1/users/list', 'v1/users/{id}'));
        $this->assertFalse(EndpointTemplate::overlaps('v1/users/{id}', 'v1/orders/static'));
        $this->assertTrue(EndpointTemplate::overlaps('v1/{package}/item', 'v1/users/{id}'));

        EndpointTemplate::assertDisjoint(['GET' => ['v1/users/list', 'v1/users/{id}/detail']]);
        $this->expectException(\LogicException::class);
        EndpointTemplate::assertDisjoint(['GET' => ['v1/users/{id}', 'v1/users/{slug}']]);
    }

    public function testEndpointMessagesUseTheRealUrl(): void
    {
        foreach (['v1/x{id}', 'v1/{id}x'] as $url) {
            try {
                EndpointTemplate::assert($url, [], 'Handler');
                $this->fail($url);
            } catch (\LogicException $exception) {
                $this->assertStringContainsString($url, $exception->getMessage());
            }
        }
        try {
            EndpointTemplate::assert('v1/users/{id}/', [], 'Handler');
            $this->fail('slash');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("endpoint URL 'v1/users/{id}'", $exception->getMessage());
            $this->assertStringNotContainsString("endpoint URL 'v1/users/{id}/'", $exception->getMessage());
        }
        try {
            EndpointTemplate::assert('v1/{id}/x/{id}/y/{id}/', ['id'], 'Handler');
            $this->fail('duplicate');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('repeats {id}.', $exception->getMessage());
            $this->assertStringContainsString("endpoint URL 'v1/{id}/x/{id}/y/{id}'", $exception->getMessage());
        }
        try {
            EndpointTemplate::assert('v1/users/{id-1}/', [], 'Handler');
            $this->fail('malformed');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("endpoint URL 'v1/users/{id-1}'", $exception->getMessage());
            $this->assertStringNotContainsString('{id-1}/', $exception->getMessage());
        }
        EndpointTemplate::assertDisjoint(['GET' => ['v1/static', 'v1/users/{id}', 'v1/orders/{id}']]);
        try {
            EndpointTemplate::assertDisjoint(['GET' => ['v1/users/{id}/', 'v1/users/{slug}/']]);
            $this->fail('overlap');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("endpoints 'v1/users/{id}' and 'v1/users/{slug}'", $exception->getMessage());
        }
    }
}
