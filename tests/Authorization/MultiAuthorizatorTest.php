<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\MultiAuthorizator;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\Misc\StaticIpDetector;

class MultiAuthorizatorTest extends TestCase
{
    public function testAllAuthorizedApiKey()
    {
        $_GET['api_key'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $queryAuthorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);

        $_SERVER['HTTP_X_API_KEY'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $headerAuthorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);

        $authorization = new MultiAuthorizator([$queryAuthorization, $headerAuthorization]);
        $this->assertTrue($authorization->authorized());
    }

    public function testFirstAuthorizedApiKey()
    {
        $_GET['api_key'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $queryAuthorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);

        $_SERVER['HTTP_X_API_KEY'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $headerAuthorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);

        $authorization = new MultiAuthorizator([$queryAuthorization, $headerAuthorization]);
        $this->assertTrue($authorization->authorized());
    }

    public function testSecondAuthorizedApiKey()
    {
        $_GET['api_key'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $queryAuthorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);

        $_SERVER['HTTP_X_API_KEY'] = 'sad0f98uwegoihweg09i4hergy';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $headerAuthorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);

        $authorization = new MultiAuthorizator([$queryAuthorization, $headerAuthorization]);
        $this->assertTrue($authorization->authorized());
    }

    public function testUnauthorizedApiKey()
    {
        $_GET['api_key'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $queryAuthorization = new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector);

        $_SERVER['HTTP_X_API_KEY'] = 'asflkhwetiohegedgfsdgwe';
        $tokenRepository = new StaticTokenRepository(['sad0f98uwegoihweg09i4hergy' => '*']);
        $ipDetector = new StaticIpDetector('34.24.126.44');
        $headerAuthorization = new HeaderApiKeyAuthentication('X-API-KEY', $tokenRepository, $ipDetector);

        $authorization = new MultiAuthorizator([$queryAuthorization, $headerAuthorization]);
        $this->assertFalse($authorization->authorized());
        $this->assertEquals('Token doesn\'t exists or isn\'t active', $authorization->getErrorMessage());
    }
}
