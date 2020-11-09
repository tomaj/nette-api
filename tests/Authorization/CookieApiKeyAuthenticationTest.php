<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;

class CookieApiKeyAuthenticationTest extends TestCase
{
    public function testAuthorizedApiKey()
    {
        $_COOKIE['api_key'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new CookieApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertTrue($authorization->authorized());
    }

    public function testUnarizedApiKey()
    {
        $_COOKIE['api_key'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new CookieApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertEquals('Token doesn\'t exists or isn\'t active', $authorization->getErrorMessage());
    }

    public function testNoApiKey()
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new CookieApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertEquals('API key is not set', $authorization->getErrorMessage());
    }

    public function testGetCookieName()
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new CookieApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertEquals('api_key', $authorization->getCookieName());
    }
}
