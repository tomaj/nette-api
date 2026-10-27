<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;

final class QueryApiKeyAuthenticationTest extends TestCase
{
    public function testAuthorizedApiKey(): void
    {
        $_GET['api_key'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertTrue($authorization->authorized());
    }

    public function testUnarizedApiKey(): void
    {
        $_GET['api_key'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertSame('Token doesn\'t exists or isn\'t active', $authorization->getErrorMessage());
    }

    public function testNoApiKey(): void
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertSame('API key is not set', $authorization->getErrorMessage());
    }

    public function testGetQueryParamName(): void
    {
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertSame('api_key', $authorization->getQueryParamName());
    }

    public function testNonStringApiKeyIsNotSet(): void
    {
        $_GET['api_key'] = ['sad0f98uwegoihweg09i4hergy'];
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $authorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);
        $this->assertFalse($authorization->authorized());
        $this->assertSame('API key is not set', $authorization->getErrorMessage());
    }
}
