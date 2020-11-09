<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;

class HeaderApiKeyAuthenticationTest extends TestCase
{
    public function testAuthorizedApiKey()
    {
        $_SERVER['HTTP_X_API_KEY'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertTrue($authorization->authorized());
    }

    public function testUnarizedApiKey()
    {
        $_SERVER['HTTP_X_API_KEY'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertEquals('Token doesn\'t exists or isn\'t active', $authorization->getErrorMessage());
    }

    public function testNoApiKey()
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertEquals('API key is not set', $authorization->getErrorMessage());
    }

    public function testGetQueryParamName()
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);
        $this->assertEquals('X-API-KEY', $authorization->getHeaderName());
    }
}
