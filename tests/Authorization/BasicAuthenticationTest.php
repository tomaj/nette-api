<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Authorization;

use PHPUnit\Framework\Attributes\BackupGlobals;
use Nette\Http\RequestFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\BasicAuthentication;

final class BasicAuthenticationTest extends TestCase
{
    private function authorization(array $authentications, string $user, string $password): BasicAuthentication
    {
        return new BasicAuthentication($authentications, $this->request('Basic ' . base64_encode($user . ':' . $password)));
    }

    private function request(?string $header): Request
    {
        return new Request(new UrlScript('https://example.com/api'), headers: $header === null ? [] : ['Authorization' => $header]);
    }

    public function testValidCredentials(): void
    {
        $this->assertTrue($this->authorization(['user' => 'secret'], 'user', 'secret')->authorized());
    }

    public function testWrongPassword(): void
    {
        $this->assertFalse($this->authorization(['user' => 'secret'], 'user', 'secret2')->authorized());
        $this->assertFalse($this->authorization(['user' => 'secret'], 'user', 'Secret')->authorized());
        $this->assertFalse($this->authorization(['user' => 'secret'], 'user', '')->authorized());
    }

    public function testUnknownUser(): void
    {
        $this->assertFalse($this->authorization(['user' => 'secret'], 'other', 'secret')->authorized());
    }

    public function testEmptyConfiguredPasswordIsRejected(): void
    {
        $this->assertFalse($this->authorization(['user' => ''], 'user', '')->authorized());
        // same as 3.5.0: '0' is falsy and was never accepted
        $this->assertFalse($this->authorization(['user' => '0'], 'user', '0')->authorized());
        $this->assertTrue($this->authorization(['user' => '00'], 'user', '00')->authorized());
    }

    public function testNonStringConfiguredPasswordIsRejectedAsBefore(): void
    {
        $this->assertFalse($this->authorization(['user' => 1234], 'user', '1234')->authorized());
    }

    public function testErrorMessage(): void
    {
        $this->assertSame('Incorrect username or password', $this->authorization([], 'a', 'b')->getErrorMessage());
    }

    public function testMissingOrMalformedHeaderIsRejected(): void
    {
        foreach ([null, '', 'Bearer abc', 'Basic', 'Basic !!!', 'Basic ' . base64_encode('user'), 'Basic ' . base64_encode('user:secret') . ' x', 'xBasic ' . base64_encode('user:secret')] as $header) {
            $this->assertFalse(new BasicAuthentication(['user' => 'secret'], $this->request($header))->authorized(), (string) $header);
        }
    }

    public function testSchemeIsCaseInsensitiveAndPasswordMayContainColon(): void
    {
        $this->assertTrue(new BasicAuthentication(['user' => 'se:cret'], $this->request('basic ' . base64_encode('user:se:cret')))->authorized());
    }

    public function testUserInUrlIsIgnored(): void
    {
        // since nette/http 3.2 the request factory puts the credentials into the Authorization header, never into the URL
        $request = new Request(new UrlScript('https://user:secret@example.com/api'));
        $this->assertFalse(new BasicAuthentication(['user' => 'secret'], $request)->authorized());
    }

    #[BackupGlobals(true)]
    public function testCredentialsFromRequestFactory(): void
    {
        $_SERVER = ['PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'secret', 'REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/api'];
        $request = new RequestFactory()->fromGlobals();
        $this->assertTrue(new BasicAuthentication(['user' => 'secret'], $request)->authorized());
    }
}
