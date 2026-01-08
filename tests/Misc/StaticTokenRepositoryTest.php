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
}
