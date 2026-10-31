<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

use Tracy\ILogger;
use Tracy\Debugger;
use Nette\Http\Request;
use Nette\Http\RequestFactory;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\IpDetector;

final class IpDetectorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    #[\Override]
    protected function setUp(): void
    {
        $this->server = $_SERVER;
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED']);
        new \ReflectionProperty(IpDetector::class, 'warned')->setValue(null, false);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    /**
     * @param array<string, string> $headers
     */
    private function warnings(bool $warn, array ...$headers): array
    {
        $tracy = new class implements ILogger {
            /** @var list<array{mixed, mixed}> */
            public array $logged = [];

            public function log(mixed $value, string $level = self::INFO): void
            {
                $this->logged[] = [$value, $level];
            }
        };
        $previous = Debugger::getLogger();
        Debugger::setLogger($tracy);
        try {
            foreach ($headers as $h) {
                $this->assertSame('10.0.0.1', new IpDetector(new Request(new UrlScript('https://api.example.com/'), headers: $h, remoteAddress: '10.0.0.1'), $warn)->getRequestIp());
            }
        } finally {
            Debugger::setLogger($previous);
        }
        return $tracy->logged;
    }

    public function testAForwardedHeaderWithoutAProxyIsLoggedOnce(): void
    {
        $this->assertSame([[
            'The request has a X-Forwarded-For header, but http: proxy: is empty, so the IP of every client is the address 10.0.0.1. Behind a reverse proxy or a load balancer list it, for example http: proxy: 10.0.0.0/8, otherwise logs, rate limits and IP restrictions of tokens see the proxy. Without a proxy ignore this, the header came from the client.',
            ILogger::WARNING,
        ]], $this->warnings(true, [], ['X-Forwarded-For' => '1.2.3.4'], ['Forwarded' => 'for=1.2.3.4'], ['X-Forwarded-For' => '1.2.3.4']));
    }

    public function testTheForwardedHeaderIsNamed(): void
    {
        $logged = $this->warnings(true, ['Forwarded' => 'for=1.2.3.4']);
        $this->assertCount(1, $logged);
        $this->assertStringStartsWith('The request has a Forwarded header,', $logged[0][0]);
    }

    public function testNoWarningWhenItIsOff(): void
    {
        $this->assertSame([], $this->warnings(false, ['X-Forwarded-For' => '1.2.3.4']));
        $this->assertSame([], $this->warnings(true, ['Accept' => 'x']));
        $tracy = $this->warnings(true, ['X-Forwarded-For' => '1.2.3.4']);
        $this->assertCount(1, $tracy, 'a request without the header does not use up the warning');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        new \ReflectionProperty(IpDetector::class, 'warned')->setValue(null, false);
        $this->assertSame('cli', new IpDetector(null, true)->getRequestIp(), 'without the request nothing to warn about');
        $this->assertFalse(new \ReflectionProperty(IpDetector::class, 'warned')->getValue());
    }

    public function testABrokenTracyFallsBackToErrorLog(): void
    {
        $tracy = new class implements ILogger {
            public function log(mixed $value, string $level = self::INFO): void
            {
                throw new \RuntimeException('no log directory');
            }
        };
        $file = (string) tempnam(sys_get_temp_dir(), 'log');
        $previousLog = ini_set('error_log', $file);
        $previous = Debugger::getLogger();
        Debugger::setLogger($tracy);
        try {
            new IpDetector(new Request(new UrlScript('https://api.example.com/'), headers: ['X-Forwarded-For' => '1.2.3.4'], remoteAddress: '10.0.0.1'), true)->getRequestIp();
        } finally {
            Debugger::setLogger($previous);
            ini_set('error_log', (string) $previousLog);
        }
        $this->assertStringContainsString('the header came from the client. (the logger failed: no log directory)', (string) file_get_contents($file));
        unlink($file);
    }

    public function testWithoutARequest(): void
    {
        $ipDetector = new IpDetector();
        $this->assertSame('cli', $ipDetector->getRequestIp());

        $_SERVER['HTTP_CLIENT_IP'] = '1.2.3.4';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $this->assertSame('cli', $ipDetector->getRequestIp(), 'the headers come from the client');

        $_SERVER['REMOTE_ADDR'] = '1.2.3.6';
        $this->assertSame('1.2.3.6', $ipDetector->getRequestIp());

        $_SERVER['REMOTE_ADDR'] = '';
        $this->assertSame('cli', $ipDetector->getRequestIp(), 'an empty address is no address');
    }

    public function testTheAddressOfTheRequestWins(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.6';
        $request = new Request(new UrlScript('https://api.example.com/'), remoteAddress: '9.9.9.9');
        $this->assertSame('9.9.9.9', new IpDetector($request)->getRequestIp());

        $request = new Request(new UrlScript('https://api.example.com/'));
        $this->assertSame('1.2.3.6', new IpDetector($request)->getRequestIp(), 'a request without an address');
    }

    /**
     * @return iterable<string, array{string, string, list<string>, string}>
     */
    public static function proxies(): iterable
    {
        yield 'no proxy configured, a forged header' => ['5.5.5.5', '1.2.3.4', [], '5.5.5.5'];
        yield 'a client that is not the proxy, a forged header' => ['5.5.5.5', '1.2.3.4', ['10.0.0.0/8'], '5.5.5.5'];
        yield 'the proxy gives the client' => ['10.0.0.1', '1.2.3.4', ['10.0.0.0/8'], '1.2.3.4'];
        yield 'a client cannot forge the start of the list' => ['10.0.0.1', '6.6.6.6, 1.2.3.4', ['10.0.0.0/8'], '1.2.3.4'];
        yield 'more proxies' => ['10.0.0.1', '1.2.3.4, 192.168.0.9', ['10.0.0.0/8', '192.168.0.0/16'], '1.2.3.4'];
        yield 'behind a proxy that is not configured, its address' => ['10.0.0.1', '1.2.3.4', [], '10.0.0.1'];
    }

    /**
     * @param list<string> $proxies
     */
    #[DataProvider('proxies')]
    public function testForwardedHeadersAreTrustedOnlyFromConfiguredProxies(string $remoteAddress, string $forwardedFor, array $proxies, string $expected): void
    {
        $_SERVER['REMOTE_ADDR'] = $remoteAddress;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        $_SERVER['HTTP_CLIENT_IP'] = '7.7.7.7';
        $factory = new RequestFactory();
        $factory->setProxy($proxies);
        $this->assertSame($expected, new IpDetector($factory->fromGlobals())->getRequestIp());
    }
}
