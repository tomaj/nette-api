<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\EndpointTemplate;

final class EndpointTemplateTest extends TestCase
{
    public function testMatchAndNormalize(): void
    {
        $this->assertSame(['id' => '42'], EndpointTemplate::match('v1/users/{id}/detail', 'v1/users/42/detail'));
        $this->assertSame(['id' => '42'], EndpointTemplate::match('v1/users/{id}/detail/', EndpointTemplate::normalize('/api/v1/users/42/detail?x=1', '1')));
        $this->assertNull(EndpointTemplate::match('v1/users/{id}/detail', 'v1/users/42'));
        $this->assertNull(EndpointTemplate::match('v1/users/{id}', 'v1/orders/42'));
        $this->assertSame('v1/users/42', EndpointTemplate::normalize('v1/users/42', '1'));
        $this->assertNull(EndpointTemplate::normalize('/api/v2/users', '1'));
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
        $this->assertNull(EndpointTemplate::normalize('/api/v12/users', '1'));
        $this->assertSame('v12/users', EndpointTemplate::normalize('/api/v12/users', '12'));
        $this->assertSame('v1', EndpointTemplate::normalize('/v1', '1'));
        $this->assertNull(EndpointTemplate::normalize('/api/v1extra', '1'));
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
}
