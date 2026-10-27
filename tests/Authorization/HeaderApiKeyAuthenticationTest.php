<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;

final class HeaderApiKeyAuthenticationTest extends TestCase
{
    public function testAuthorizedApiKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertTrue($authorization->authorized());
    }

    public function testUnarizedApiKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertSame('Token doesn\'t exists or isn\'t active', $authorization->getErrorMessage());
    }

    public function testNoApiKey(): void
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertSame('API key is not set', $authorization->getErrorMessage());
    }

    public function testGetQueryParamName(): void
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertSame('X-API-KEY', $authorization->getHeaderName());
    }

    public function testNonStringApiKeyIsNotSet(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = ['sad0f98uwegoihweg09i4hergy'];
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertSame('API key is not set', $authorization->getErrorMessage());
    }
}
