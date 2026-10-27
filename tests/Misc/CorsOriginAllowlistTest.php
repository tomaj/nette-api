<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

use PHPUnit\Framework\Attributes\DataProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\CorsOriginAllowlist;

final class CorsOriginAllowlistTest extends TestCase
{
    public function testAllowedOriginIsReflected(): void
    {
        $allowlist = new CorsOriginAllowlist(['https://app.example.com', 'http://localhost:3000']);

        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://app.example.com'], $allowlist->headers('https://app.example.com'));
        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'http://localhost:3000'], $allowlist->headers('http://localhost:3000'));
    }

    public function testComparisonIsCaseInsensitiveAndReflectsRequestValue(): void
    {
        $allowlist = new CorsOriginAllowlist(['https://App.Example.com']);

        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'HTTPS://APP.EXAMPLE.COM'], $allowlist->headers('HTTPS://APP.EXAMPLE.COM'));
    }

    public function testOtherOriginsGetNoAllowOrigin(): void
    {
        $allowlist = new CorsOriginAllowlist(['https://app.example.com'], true);

        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://evil.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com.evil.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('http://app.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com:8443'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('null'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers(''));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com/'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers(' https://app.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com, https://evil.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://evil.example.com, https://app.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers(null));
    }

    public function testDefaultPortsAreDroppedLikeBrowsersDo(): void
    {
        $allowlist = new CorsOriginAllowlist(['https://a.example.com:443', 'HTTP://B.example.com:80', 'https://c.example.com:80', 'http://d.example.com:443']);

        $this->assertSame('https://a.example.com', $allowlist->headers('https://a.example.com')['Access-Control-Allow-Origin'] ?? null);
        $this->assertSame('http://b.example.com', $allowlist->headers('http://b.example.com')['Access-Control-Allow-Origin'] ?? null);
        $this->assertSame('https://c.example.com:80', $allowlist->headers('https://c.example.com:80')['Access-Control-Allow-Origin'] ?? null);
        $this->assertSame('http://d.example.com:443', $allowlist->headers('http://d.example.com:443')['Access-Control-Allow-Origin'] ?? null);
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://c.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $allowlist->headers('https://a.example.com:443'));
    }

    public function testOtherValidForms(): void
    {
        $allowlist = new CorsOriginAllowlist(['http://[::1]:8080', 'capacitor://localhost', 'https://xn--bcher-kva.example', 'http://localhost:1', 'http://localhost:65535']);

        foreach (['http://[::1]:8080', 'capacitor://localhost', 'https://xn--bcher-kva.example', 'http://localhost:1', 'http://localhost:65535'] as $origin) {
            $this->assertSame($origin, $allowlist->headers($origin)['Access-Control-Allow-Origin'] ?? null, $origin);
        }
    }

    public function testCredentials(): void
    {
        $allowlist = new CorsOriginAllowlist(['https://app.example.com'], true);

        $this->assertSame([
            'Vary' => 'Origin',
            'Access-Control-Allow-Origin' => 'https://app.example.com',
            'Access-Control-Allow-Credentials' => 'true',
        ], $allowlist->headers('https://app.example.com'));
    }

    public function testEmptyListIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CORS allowlist must contain at least one origin');
        new CorsOriginAllowlist([]);
    }

    #[DataProvider('invalidOrigins')]
    public function testInvalidOriginIsRejected(string $origin): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CORS origin "' . $origin . '", expected scheme://host[:port] without path, e.g. "https://app.example.com"');
        new CorsOriginAllowlist(['https://ok.example.com', $origin]);
    }

    public static function invalidOrigins(): \Iterator
    {
        yield 'wildcard' => ['*'];
        yield 'null' => ['null'];
        yield 'host only' => ['app.example.com'];
        yield 'trailing slash' => ['https://app.example.com/'];
        yield 'path' => ['https://app.example.com/api'];
        yield 'query' => ['https://app.example.com?a=1'];
        yield 'fragment' => ['https://app.example.com#a'];
        yield 'user' => ['https://user@app.example.com'];
        yield 'password' => ['https://user:pass@app.example.com'];
        yield 'unparsable' => ['http:///example.com'];
        yield 'scheme only' => ['https://'];
        yield 'empty port' => ['https://app.example.com:'];
        yield 'port 0' => ['https://app.example.com:0'];
        yield 'port with leading zero' => ['https://app.example.com:0443'];
        yield 'port too big' => ['https://app.example.com:65536'];
        yield 'leading space' => [' https://app.example.com'];
        yield 'trailing space' => ['https://app.example.com '];
        yield 'trailing newline' => ["https://app.example.com\n"];
        yield 'unicode host' => ['https://bücher.example'];
        yield 'no scheme' => ['//app.example.com'];
        yield 'empty' => [''];
    }
}
