<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

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
    }

    #[\Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->server;
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
