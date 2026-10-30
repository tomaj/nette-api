<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Logger;

use Tomaj\NetteApi\Logger\LogValueMaskerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Logger\LogMaskMode;
use Tomaj\NetteApi\Logger\LogMasking;
use Tomaj\NetteApi\Logger\LogValueMasker;

final class LogMaskingTest extends TestCase
{
    private static function lines(string $logged): string
    {
        return str_replace(PHP_EOL, "\n", $logged);
    }

    public static function uris(): iterable
    {
        yield 'no query' => ['/api/v1/users', '/api/v1/users'];
        yield 'not masked' => ['/api/v1/users?page=2&q=a%20b', '/api/v1/users?page=2&q=a%20b'];
        yield 'masked' => ['/api/v1/users?api_key=abc&page=2', '/api/v1/users?api_key=***&page=2'];
        yield 'case insensitive' => ['/x?API_KEY=abc&Token=t', '/x?API_KEY=***&Token=***'];
        yield 'brackets' => ['/x?token[]=a&token[b]=c&token%5B%5D=d', '/x?token[]=***&token[b]=***&token%5B%5D=***'];
        yield 'encoded name' => ['/x?api%5Fkey=abc', '/x?api%5Fkey=***'];
        yield 'no value' => ['/x?token&code=', '/x?token&code=***'];
        yield 'empty query' => ['/x?', '/x?'];
        yield 'all defaults' => ['/x?apikey=1&key=1&access_token=1&refresh_token=1&id_token=1&auth=1&password=1&secret=1&signature=1&sig=1&code=1&client_secret=1', '/x?apikey=***&key=***&access_token=***&refresh_token=***&id_token=***&auth=***&password=***&secret=***&signature=***&sig=***&code=***&client_secret=***'];
        yield 'question mark in query' => ['/x?token=a?b&c=d?e', '/x?token=***&c=d?e'];
        yield 'equals in value' => ['/x?token=a=b&c=d=e', '/x?token=***&c=d=e'];
    }

    #[DataProvider('uris')]
    public function testUri(string $uri, string $expected): void
    {
        $this->assertSame($expected, new LogMasking()->uri($uri));
    }

    public function testUriAuthParamsAndModes(): void
    {
        $masking = new LogMasking();
        $this->assertSame('/x?partner=***&page=1', $masking->uri('/x?partner=abc&page=1', ['Partner']));

        $masking->setMasker(new LogValueMasker(LogMaskMode::Omit));
        $this->assertSame('/x?page=1', $masking->uri('/x?token=a&page=1&code=b'));
        $this->assertSame('/x', $masking->uri('/x?token=a&code=b'));

        $masking->setMasker(new LogValueMasker(LogMaskMode::Partial));
        $this->assertSame('/x?token=Bearer%20abcd***&code=***', $masking->uri('/x?token=Bearer%20abcdefghijkl&code=b'));

        $masking->setMasker(new LogValueMasker(LogMaskMode::Hash));
        $this->assertSame('/x?token=hash:' . substr(hash('sha256', 'a b'), 0, 16), $masking->uri('/x?token=a+b'));
    }

    public function testUriMaskedValueIsEncoded(): void
    {
        $masking = new LogMasking();
        $masking->setMasker(new class implements LogValueMaskerInterface {
            public function mask(string $value): ?string
            {
                return 'v=' . $value . '&*:';
            }
        });
        $this->assertSame('/x?token=v%3Da%26b%26*:', $masking->uri('/x?token=a%26b'));
    }

    public function testQueryMaskingOff(): void
    {
        $masking = new LogMasking();
        $masking->setQueryParams([], 'setX');
        $this->assertSame([], $masking->getQueryParams());
        $this->assertSame('/x?token=a&partner=b', $masking->uri('/x?token=a&partner=b', ['partner']), 'off leaves the authorization names too');

        $masking->addQueryParam('Session', 'addX');
        $this->assertSame(['Session'], $masking->getQueryParams());
        $this->assertSame('/x?token=a&session=***&partner=***', $masking->uri('/x?token=a&session=b&partner=c', ['partner']));
    }

    public function testQueryParamsAddRemove(): void
    {
        $masking = new LogMasking();
        $masking->removeQueryParam('CODE', 'removeX');
        $masking->addQueryParam('session_id', 'addX');
        $masking->addQueryParam('SESSION_ID', 'addX');
        $this->assertSame(['api_key', 'apikey', 'key', 'token', 'access_token', 'refresh_token', 'id_token', 'auth', 'password', 'secret', 'signature', 'sig', 'client_secret', 'SESSION_ID'], $masking->getQueryParams());
        $this->assertSame('/x?code=a&session_id=***', $masking->uri('/x?code=a&session_id=b'));

        $masking->setQueryParams(['a', 'b'], 'setX');
        $this->assertSame(['a', 'b'], $masking->getQueryParams());
    }

    public function testHeaders(): void
    {
        $masking = new LogMasking();
        $headers = ['Authorization' => 'Bearer t', 'x-auth-user' => 'u', 'X-Request-Id' => 'r', 'X-Session-KEY' => 'k', 'X-Partner' => 'p', 'Accept' => ['x'], 5 => 'n'];
        $this->assertSame("Authorization: ***\nx-auth-user: ***\nX-Request-Id: r\nX-Session-KEY: ***\nX-Partner: ***\nAccept: \n5: n\n", self::lines($masking->headers($headers, ['x-PARTNER'])));
    }

    public function testNonStringHeaderValues(): void
    {
        $this->assertSame("X-Token: ***\nX-Count: 5\n", self::lines(new LogMasking()->headers(['X-Token' => 5, 'X-Count' => 5])));
    }

    public function testCookieValueGoesToTheMasker(): void
    {
        $masking = new LogMasking();
        $masking->removeHeader('Cookie', 'removeX');
        $masking->setMasker(new LogValueMasker(LogMaskMode::Hash));
        $this->assertSame('Cookie: session=hash:' . substr(hash('sha256', 'a=b'), 0, 16) . "\n", self::lines($masking->headers(['Cookie' => 'session=a=b'], [], ['session'])));
    }

    public function testRemoveHeaderHintsAnUppercasePattern(): void
    {
        $masking = new LogMasking();
        $masking->addHeaderPattern('X-Foo-*', 'addX');
        $this->expectExceptionMessage("It is masked by the pattern 'X-Foo-*'");
        $masking->removeHeader('x-foo-bar', 'removeX');
    }

    public function testHeaderPatternMatchesWholeName(): void
    {
        $masking = new LogMasking();
        $masking->setHeaderPatterns(['x-auth-*', 'a.b*'], 'setX');
        $this->assertSame("ax-auth-y: 1\nx-auth-: ***\naxb: 2\na.bc: ***\nCookie: ***\n", self::lines($masking->headers(['ax-auth-y' => '1', 'x-auth-' => 's', 'axb' => '2', 'a.bc' => 's', 'Cookie' => 'c'])));
    }

    public function testAuthCookies(): void
    {
        $masking = new LogMasking();
        $masking->removeHeader('Cookie', 'removeX');
        $header = ['Cookie' => 'a=1;  session=s; Session=S; bad; x=y=z'];
        $this->assertSame("Cookie: a=1; session=***; Session=S; bad; x=y=z\n", self::lines($masking->headers($header, [], ['session'])));
        $this->assertSame("Cookie: a=1;  session=s; Session=S; bad; x=y=z\n", self::lines($masking->headers($header)));

        $masking->setMasker(new LogValueMasker(LogMaskMode::Omit));
        $this->assertSame("Cookie: a=1; Session=S; bad; x=y=z\n", self::lines($masking->headers($header, [], ['session'])));

        $masking->addHeader('cookie', 'addX');
        $this->assertSame('', $masking->headers($header, [], ['session']), 'a masked Cookie header masks the whole value');
    }

    public function testHeadersOff(): void
    {
        $masking = new LogMasking();
        $masking->setHeaders([], 'setX');
        $this->assertSame([], $masking->getHeaders());
        $this->assertSame([], $masking->getHeaderPatterns());
        $this->assertSame("Authorization: t\nX-Partner: p\nCookie: s=1\n", self::lines($masking->headers(['Authorization' => 't', 'X-Partner' => 'p', 'Cookie' => 's=1'], ['X-Partner'], ['s'])));

        $masking->addHeaderPattern('*partner', 'addX');
        $this->assertSame("Authorization: t\nX-Partner: ***\nCookie: s=***\n", self::lines($masking->headers(['Authorization' => 't', 'X-Partner' => 'p', 'Cookie' => 's=1'], ['X-Partner'], ['s'])));
    }

    public function testHeadersOnAgainAfterAdd(): void
    {
        $masking = new LogMasking();
        $masking->setHeaders([], 'setX');
        $masking->addHeader('X-A', 'addX');
        $this->assertSame("X-A: ***\nX-B: ***\n", self::lines($masking->headers(['X-A' => '1', 'X-B' => '2'], ['x-b'])));

        $masking->setHeaders([], 'setX');
        $masking->setHeaderPatterns(['x-*'], 'setX');
        $this->assertSame("X-A: ***\n", self::lines($masking->headers(['X-A' => '1'])));

        $masking->setHeaders([], 'setX');
        $masking->setHeaderPatterns([], 'setX');
        $this->assertSame("X-A: 1\n", self::lines($masking->headers(['X-A' => '1'], ['x-a'])));
    }

    public function testPatternsOffKeepsHeaders(): void
    {
        $masking = new LogMasking();
        $masking->setHeaderPatterns([], 'setX');
        $this->assertSame("X-Token: t\nX-B: ***\n", self::lines($masking->headers(['X-Token' => 't', 'X-B' => '2'], ['X-B'])));
    }

    public function testRemoveLastHeaderKeepsPatterns(): void
    {
        $masking = new LogMasking();
        $masking->setHeaders(['X-A'], 'setX');
        $masking->removeHeader('x-a', 'removeX');
        $masking->removeHeaderPattern('*TOKEN*', 'removeX');
        $this->assertSame(['*secret*', '*key*', 'x-auth-*'], $masking->getHeaderPatterns());
        $this->assertSame("X-A: 1\nX-Token: 2\nX-Key: ***\n", self::lines($masking->headers(['X-A' => '1', 'X-Token' => '2', 'X-Key' => '3'])));
        $masking->addHeaderPattern('X-T*', 'addX');
        $masking->addHeaderPattern('x-t*', 'addX');
        $this->assertSame(['*secret*', '*key*', 'x-auth-*', 'x-t*'], $masking->getHeaderPatterns());
    }

    public static function errors(): iterable
    {
        $defaults = 'Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key';
        yield 'add bad header' => [['addHeader', 'X Secret', 'addMaskedLogHeader'], "addMaskedLogHeader('X Secret'): it is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, for example X-Session."];
        yield 'add header with star' => [['addHeader', 'X-*', 'addMaskedLogHeader'], "addMaskedLogHeader('X-*'): it contains *, which is only for header patterns. Add it as a pattern, or write the full header name."];
        yield 'set bad header' => [['setHeaders', ['Authorization', 'X-A:'], 'setMaskedLogHeaders'], "setMaskedLogHeaders(): 'X-A:' in the list: it is not an HTTP header name"];
        yield 'remove missing header' => [['removeHeader', 'Autorization', 'removeMaskedLogHeader'], "removeMaskedLogHeader('Autorization'): 'Autorization' is not in the masked headers ($defaults), so there is nothing to remove. Did you mean 'Authorization'? Entries compare case-insensitively; fix the spelling or leave the entry out."];
        yield 'remove header masked by pattern' => [['removeHeader', 'X-Auth-Token', 'removeMaskedLogHeader'], "removeMaskedLogHeader('X-Auth-Token'): 'X-Auth-Token' is not in the masked headers ($defaults), so there is nothing to remove. It is masked by the pattern '*token*'; remove the pattern, or replace the patterns with a narrower list."];
        yield 'remove from empty' => [['setHeaders([], set)|removeHeader', 'X-A', 'removeMaskedLogHeader'], "removeMaskedLogHeader('X-A'): 'X-A' is not in the masked headers (the list is empty), so there is nothing to remove. Entries"];
        yield 'pattern without star' => [['addHeaderPattern', 'token', 'addMaskedLogHeaderPattern'], "addMaskedLogHeaderPattern('token'): it has no *, so it matches only one header. Add it as a header name, or use * for any characters, for example *token*."];
        yield 'pattern only stars' => [['setHeaderPatterns', ['**'], 'setMaskedLogHeaderPatterns'], "setMaskedLogHeaderPatterns(): '**' in the list: it matches every header, so nothing useful would be logged."];
        yield 'pattern bad chars' => [['addHeaderPattern', 'x auth*', 'addMaskedLogHeaderPattern'], "addMaskedLogHeaderPattern('x auth*'): it is not a header pattern, a pattern is a header name with * for any characters"];
        yield 'remove missing pattern' => [['removeHeaderPattern', '*secrets*', 'removeMaskedLogHeaderPattern'], "removeMaskedLogHeaderPattern('*secrets*'): '*secrets*' is not in the masked header patterns (*token*, *secret*, *key*, x-auth-*), so there is nothing to remove. Did you mean '*secret*'?"];
        yield 'bad param' => [['addQueryParam', 'token[]', 'addMaskedLogQueryParam'], "addMaskedLogQueryParam('token[]'): it is not a query parameter name. Write only the name without =, &, ?, # or brackets, for example api_key (it also masks api_key[] and api_key[x])."];
        yield 'empty param' => [['setQueryParams', [''], 'setMaskedLogQueryParams'], "setMaskedLogQueryParams(): '' in the list: it is not a query parameter name."];
        foreach (['a b', 'a=b', 'a&b', 'a#b', 'a?b', 'a]'] as $param) {
            yield "param $param" => [['addQueryParam', $param, 'add'], "add('$param'): it is not a query parameter name."];
        }
        yield 'remove missing param' => [['removeQueryParam', 'acess_token', 'removeMaskedLogQueryParam'], "removeMaskedLogQueryParam('acess_token'): 'acess_token' is not in the masked query parameters (api_key, apikey, key, token, access_token, refresh_token, id_token, auth, password, secret, signature, sig, code, client_secret), so there is nothing to remove. Did you mean 'access_token'?"];
    }

    #[DataProvider('errors')]
    public function testErrors(array $call, string $message): void
    {
        $masking = new LogMasking();
        [$method, $value, $name] = $call;
        if (str_starts_with($method, 'setHeaders([], set)|')) {
            $masking->setHeaders([], 'set');
            $method = substr($method, strlen('setHeaders([], set)|'));
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $masking->$method($value, $name);
    }

    public function testValidNamesPass(): void
    {
        $this->assertNull(LogMasking::headerNameProblem("X-A_b.1!#$%&'+^`|~"));
        $this->assertNull(LogMasking::headerPatternProblem('*x*'));
        $this->assertNull(LogMasking::queryParamProblem('a.b-c'));
    }

    public function testDefaultMasker(): void
    {
        $masking = new LogMasking();
        $this->assertInstanceOf(LogValueMasker::class, $masking->getMasker());
        $this->assertSame(LogMaskMode::Full, $masking->getMasker()->getMode());
    }
}
