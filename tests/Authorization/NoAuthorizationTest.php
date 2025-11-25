<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;

class NoAuthorizationTest extends TestCase
{
    public function testResponse()
    {
        $noAuthorization = new NoAuthorization();
        self::assertTrue($noAuthorization->authorized());
        self::assertNull($noAuthorization->getErrorMessage());
    }
}
