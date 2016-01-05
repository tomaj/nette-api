<?php

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit_Framework_TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;

class NoAuthorizationTest extends PHPUnit_Framework_TestCase
{
    public function testResponse()
    {
        $noAuthorization = new NoAuthorization();
        $this->assertTrue($noAuthorization->authorized());
        $this->assertFalse($noAuthorization->getErrorMessage());
    }
}
