<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;

final class BearerTokenAuthorizationTest extends TestCase
{
    public function testAuthorizedToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sad0f98uwegoihweg09i4hergy';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertTrue($bearerTokenAuthorization->authorized());
    }

    public function testUnarizedToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer asflkhwetiohegedgfsdgwe';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
        $this->assertSame('Token doesn\'t exists or isn\'t active', $bearerTokenAuthorization->getErrorMessage());
    }

    public function testWrongAuthorizationFormat(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'B asflkhwetiohegedgfsdgwe asd';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
        $this->assertSame('Authorization header contains invalid structure', $bearerTokenAuthorization->getErrorMessage());
    }

    public function testWrongBearerAuthorizationFormat(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bdsdsdssd asflkhwetiohegedgfsdgwe';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
        $this->assertSame('Authorization header doesn\'t contain bearer token', $bearerTokenAuthorization->getErrorMessage());
    }

    public function testNoAuthorizationHeader(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
        $this->assertSame('Authorization header HTTP_Authorization is not set', $bearerTokenAuthorization->getErrorMessage());
    }

    public function testIpRestrictionWithValidIp(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sad0f98uwegoihweg09i4hergy';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '34.24.126.44']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertTrue($bearerTokenAuthorization->authorized());
    }

    public function testIpRestrictionWithoutValidIp(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sad0f98uwegoihweg09i4hergy';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '34.24.126.45']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
        $this->assertSame('Invalid IP', $bearerTokenAuthorization->getErrorMessage());
    }

    public function testIpInRange(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sad0f98uwegoihweg09i4hergy';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '192.168.0.0/24']);
        $ipDetector = new StaticIpDetector('192.168.0.33');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertTrue($bearerTokenAuthorization->authorized());
    }

    public function testTokenWithMultipleIps(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sad0f98uwegoihweg09i4hergy';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '124.23.12.42,5.6.2.1']);

        $ipDetector = new StaticIpDetector('5.6.2.1');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertTrue($bearerTokenAuthorization->authorized());

        $ipDetector = new StaticIpDetector('5.6.2.2');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
    }

    public function testTokenWithDisabledAccess(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer sad0f98uwegoihweg09i4hergy';
        $bearerTokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => null]);

        $ipDetector = new StaticIpDetector('5.6.2.1');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());

        $ipDetector = new StaticIpDetector('5.6.2.2');
        $bearerTokenAuthorization = new BearerTokenAuthorization($bearerTokenRepository, $ipDetector);
        $this->assertFalse($bearerTokenAuthorization->authorized());
    }
}
