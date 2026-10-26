<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;

/** BearerTokenAuthorization reads $_SERVER['HTTP_AUTHORIZATION'], so the test sets it. */
final class AuthorizationTest extends TestCase
{
    private BearerTokenAuthorization $authorization;

    protected function setUp(): void
    {
        $this->authorization = new BearerTokenAuthorization(
            new StaticTokenRepository(['test-token' => '*', 'office-token' => '10.0.0.1']),
            new StaticIpDetector('127.0.0.1'),
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testValidToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';

        $this->assertTrue($this->authorization->authorized());
    }

    public function testMissingHeader(): void
    {
        $this->assertFalse($this->authorization->authorized());
        $this->assertSame('Authorization header HTTP_Authorization is not set', $this->authorization->getErrorMessage());
    }

    public function testTokenFromAnotherIp(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer office-token';

        $this->assertFalse($this->authorization->authorized());
    }
}
