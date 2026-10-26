<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\StaticTokenRepository;

class StaticTokenRepositoryTest extends TestCase
{
    public function testValidation()
    {
        $repository = new StaticTokenRepository([
            'mytoken' => '*',
        ]);

        self::assertTrue($repository->validToken('mytoken'));
        self::assertFalse($repository->validToken('mytoken2'));
        self::assertEquals('*', $repository->ipRestrictions('mytoken'));
    }

    public function testIpRestrictionsForInvalidToken()
    {
        $repository = new StaticTokenRepository([
            'mytoken' => '*',
        ]);
        self::assertNull($repository->ipRestrictions('mytoken2'));
    }

    public function testMultipleTokens()
    {
        $repository = new StaticTokenRepository([
            'first' => '*',
            'second' => '127.0.0.1',
            'third' => '*',
        ]);

        self::assertTrue($repository->validToken('first'));
        self::assertTrue($repository->validToken('second'));
        self::assertTrue($repository->validToken('third'));
        self::assertFalse($repository->validToken('fourth'));
        self::assertFalse($repository->validToken(''));
        self::assertFalse($repository->validToken('firs'));
    }

    public function testEmptyRepository()
    {
        self::assertFalse((new StaticTokenRepository())->validToken('anything'));
    }

    public function testNumericTokenIsRejectedAsBefore()
    {
        // PHP casts the array key '123' to int 123; the strict in_array() never matched it
        $repository = new StaticTokenRepository(['123' => '*', 'abc' => '*']);

        self::assertFalse($repository->validToken('123'));
        self::assertTrue($repository->validToken('abc'));
    }
}
