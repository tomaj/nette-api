<?php

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\StaticBearerTokenRepository;

class StaticBearerTokenRepositoryTest extends TestCase
{
    public function testValidation()
    {
        $repository = new StaticBearerTokenRepository([
            'mytoken' => '*',
        ]);

        $this->assertTrue($repository->validToken('mytoken'));
        $this->assertFalse($repository->validToken('mytoken2'));
        $this->assertEquals('*', $repository->ipRestrictions('mytoken'));
    }

    public function testIpRestrictionsForInvalidToken()
    {
        $repository = new StaticBearerTokenRepository([
            'mytoken' => '*',
        ]);
        $this->assertFalse($repository->ipRestrictions('mytoken2'));
    }
}
