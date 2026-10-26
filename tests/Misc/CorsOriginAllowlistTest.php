<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\CorsOriginAllowlist;

class CorsOriginAllowlistTest extends TestCase
{
    public function testAllowedOriginIsReflected()
    {
        $allowlist = new CorsOriginAllowlist(['https://app.example.com', 'http://localhost:3000']);

        self::assertSame(
            ['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://app.example.com'],
            $allowlist->headers('https://app.example.com')
        );
        self::assertSame(
            ['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'http://localhost:3000'],
            $allowlist->headers('http://localhost:3000')
        );
    }

    public function testComparisonIsCaseInsensitiveAndReflectsRequestValue()
    {
        $allowlist = new CorsOriginAllowlist(['https://App.Example.com']);

        self::assertSame(
            ['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'HTTPS://APP.EXAMPLE.COM'],
            $allowlist->headers('HTTPS://APP.EXAMPLE.COM')
        );
    }

    public function testOtherOriginsGetNoAllowOrigin()
    {
        $allowlist = new CorsOriginAllowlist(['https://app.example.com'], true);

        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://evil.example.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com.evil.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('http://app.example.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com:8443'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('null'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers(''));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com/'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers(' https://app.example.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://app.example.com, https://evil.example.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://evil.example.com, https://app.example.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers(null));
    }

    public function testDefaultPortsAreDroppedLikeBrowsersDo()
    {
        $allowlist = new CorsOriginAllowlist(['https://a.example.com:443', 'HTTP://B.example.com:80', 'https://c.example.com:80', 'http://d.example.com:443']);

        self::assertSame('https://a.example.com', $allowlist->headers('https://a.example.com')['Access-Control-Allow-Origin'] ?? null);
        self::assertSame('http://b.example.com', $allowlist->headers('http://b.example.com')['Access-Control-Allow-Origin'] ?? null);
        self::assertSame('https://c.example.com:80', $allowlist->headers('https://c.example.com:80')['Access-Control-Allow-Origin'] ?? null);
        self::assertSame('http://d.example.com:443', $allowlist->headers('http://d.example.com:443')['Access-Control-Allow-Origin'] ?? null);
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://c.example.com'));
        self::assertSame(['Vary' => 'Origin'], $allowlist->headers('https://a.example.com:443'));
    }

    public function testOtherValidForms()
    {
        $allowlist = new CorsOriginAllowlist(['http://[::1]:8080', 'capacitor://localhost', 'https://xn--bcher-kva.example', 'http://localhost:1', 'http://localhost:65535']);

        foreach (['http://[::1]:8080', 'capacitor://localhost', 'https://xn--bcher-kva.example', 'http://localhost:1', 'http://localhost:65535'] as $origin) {
            self::assertSame($origin, $allowlist->headers($origin)['Access-Control-Allow-Origin'] ?? null, $origin);
        }
    }

    public function testCredentials()
    {
        $allowlist = new CorsOriginAllowlist(['https://app.example.com'], true);

        self::assertSame(
            [
                'Vary' => 'Origin',
                'Access-Control-Allow-Origin' => 'https://app.example.com',
                'Access-Control-Allow-Credentials' => 'true',
            ],
            $allowlist->headers('https://app.example.com')
        );
    }

    public function testEmptyListIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CORS allowlist must contain at least one origin');
        new CorsOriginAllowlist([]);
    }

    /**
     * @dataProvider invalidOrigins
     */
    public function testInvalidOriginIsRejected(string $origin)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CORS origin "' . $origin . '", expected scheme://host[:port] without path, e.g. "https://app.example.com"');
        new CorsOriginAllowlist(['https://ok.example.com', $origin]);
    }

    public static function invalidOrigins(): array
    {
        return [
            'wildcard' => ['*'],
            'null' => ['null'],
            'host only' => ['app.example.com'],
            'trailing slash' => ['https://app.example.com/'],
            'path' => ['https://app.example.com/api'],
            'query' => ['https://app.example.com?a=1'],
            'fragment' => ['https://app.example.com#a'],
            'user' => ['https://user@app.example.com'],
            'password' => ['https://user:pass@app.example.com'],
            'unparsable' => ['http:///example.com'],
            'scheme only' => ['https://'],
            'empty port' => ['https://app.example.com:'],
            'port 0' => ['https://app.example.com:0'],
            'port with leading zero' => ['https://app.example.com:0443'],
            'port too big' => ['https://app.example.com:65536'],
            'leading space' => [' https://app.example.com'],
            'trailing space' => ['https://app.example.com '],
            'trailing newline' => ["https://app.example.com\n"],
            'unicode host' => ['https://bücher.example'],
            'no scheme' => ['//app.example.com'],
            'empty' => [''],
        ];
    }
}
