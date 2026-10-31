<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Authorization;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;

final class AuthorizationIdentityTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testTokenIsTheIdentity(): void
    {
        $authorization = new BearerTokenAuthorization(new StaticTokenRepository(['good' => '*', 'local' => '127.0.0.1']), new IpDetector());
        $this->assertInstanceOf(AuthorizationIdentityInterface::class, $authorization);
        $this->assertNull($authorization->getIdentity());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer good';
        $this->assertTrue($authorization->authorized());
        $this->assertSame('good', $authorization->getIdentity());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer bad';
        $this->assertFalse($authorization->authorized());
        $this->assertNull($authorization->getIdentity(), 'a failed authorization clears the identity');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer good';
        $authorization->authorized();
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer local';
        $this->assertFalse($authorization->authorized(), 'the IP is not allowed');
        $this->assertNull($authorization->getIdentity());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer good';
        $authorization->authorized();
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $this->assertFalse($authorization->authorized());
        $this->assertNull($authorization->getIdentity(), 'a missing token clears the identity');
    }

    private function basic(string $user, string $password): BasicAuthentication
    {
        return new BasicAuthentication(['user' => 'secret'], new Request(new UrlScript('https://example.com/api'), headers: ['Authorization' => 'Basic ' . base64_encode($user . ':' . $password)]));
    }

    public function testUserIsTheIdentity(): void
    {
        $authorization = $this->basic('user', 'secret');
        $this->assertNull($authorization->getIdentity());
        $this->assertTrue($authorization->authorized());
        $this->assertSame('user', $authorization->getIdentity());

        $authorization = $this->basic('user', 'wrong');
        $this->assertFalse($authorization->authorized());
        $this->assertNull($authorization->getIdentity());
    }
}
