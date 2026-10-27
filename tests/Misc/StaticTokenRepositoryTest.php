<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\StaticTokenRepository;

final class StaticTokenRepositoryTest extends TestCase
{
    public function testValidation(): void
    {
        $repository = new StaticTokenRepository([
            'mytoken' => '*',
        ]);

        $this->assertTrue($repository->validToken('mytoken'));
        $this->assertFalse($repository->validToken('mytoken2'));
        $this->assertSame('*', $repository->ipRestrictions('mytoken'));
    }

    public function testIpRestrictionsForInvalidToken(): void
    {
        $repository = new StaticTokenRepository([
            'mytoken' => '*',
        ]);
        $this->assertNull($repository->ipRestrictions('mytoken2'));
    }

    public function testMultipleTokens(): void
    {
        $repository = new StaticTokenRepository([
            'first' => '*',
            'second' => '127.0.0.1',
            'third' => '*',
        ]);

        $this->assertTrue($repository->validToken('first'));
        $this->assertTrue($repository->validToken('second'));
        $this->assertTrue($repository->validToken('third'));
        $this->assertFalse($repository->validToken('fourth'));
        $this->assertFalse($repository->validToken(''));
        $this->assertFalse($repository->validToken('firs'));
    }

    public function testEmptyRepository(): void
    {
        $this->assertFalse(new StaticTokenRepository()->validToken('anything'));
    }

    public function testNumericTokenIsRejectedAsBefore(): void
    {
        // PHP casts the array key '123' to int 123; the strict in_array() never matched it
        $repository = new StaticTokenRepository(['123' => '*', 'abc' => '*']);

        $this->assertFalse($repository->validToken('123'));
        $this->assertTrue($repository->validToken('abc'));
    }
}
