<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;

final class NoAuthorizationTest extends TestCase
{
    public function testResponse(): void
    {
        $noAuthorization = new NoAuthorization();
        $this->assertTrue($noAuthorization->authorized());
        $this->assertNull($noAuthorization->getErrorMessage());
    }
}
