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

        $this->assertTrue($repository->validToken('mytoken'));
        $this->assertFalse($repository->validToken('mytoken2'));
        $this->assertEquals('*', $repository->ipRestrictions('mytoken'));
    }

    public function testIpRestrictionsForInvalidToken()
    {
        $repository = new StaticTokenRepository([
            'mytoken' => '*',
        ]);
        $this->assertNull($repository->ipRestrictions('mytoken2'));
    }
}
