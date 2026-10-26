<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Authorization;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\BasicAuthentication;

class BasicAuthenticationTest extends TestCase
{
    private function authorization(array $authentications, string $user, string $password): BasicAuthentication
    {
        $url = new UrlScript('https://' . rawurlencode($user) . ':' . rawurlencode($password) . '@example.com/api');
        return new BasicAuthentication($authentications, new Request($url));
    }

    public function testValidCredentials()
    {
        self::assertTrue($this->authorization(['user' => 'secret'], 'user', 'secret')->authorized());
    }

    public function testWrongPassword()
    {
        self::assertFalse($this->authorization(['user' => 'secret'], 'user', 'secret2')->authorized());
        self::assertFalse($this->authorization(['user' => 'secret'], 'user', 'Secret')->authorized());
        self::assertFalse($this->authorization(['user' => 'secret'], 'user', '')->authorized());
    }

    public function testUnknownUser()
    {
        self::assertFalse($this->authorization(['user' => 'secret'], 'other', 'secret')->authorized());
    }

    public function testEmptyConfiguredPasswordIsRejected()
    {
        self::assertFalse($this->authorization(['user' => ''], 'user', '')->authorized());
        // same as 3.5.0: '0' is falsy and was never accepted
        self::assertFalse($this->authorization(['user' => '0'], 'user', '0')->authorized());
        self::assertTrue($this->authorization(['user' => '00'], 'user', '00')->authorized());
    }

    public function testNonStringConfiguredPasswordIsRejectedAsBefore()
    {
        self::assertFalse($this->authorization(['user' => 1234], 'user', '1234')->authorized());
    }

    public function testErrorMessage()
    {
        self::assertSame('Incorrect username or password', $this->authorization([], 'a', 'b')->getErrorMessage());
    }
}
