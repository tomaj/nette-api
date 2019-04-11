<?php

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;

class NoAuthorizationTest extends TestCase
{
    public function testResponse()
    {
        $noAuthorization = new NoAuthorization();
        $this->assertTrue($noAuthorization->authorized());
        $this->assertFalse($noAuthorization->getErrorMessage());
    }
}
