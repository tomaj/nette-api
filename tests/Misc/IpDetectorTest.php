<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\IpDetector;

final class IpDetectorTest extends TestCase
{
    public function testValidation(): void
    {
        $ipDetector = new IpDetector();
        $this->assertSame('cli', $ipDetector->getRequestIp());

        $_SERVER['HTTP_CLIENT_IP'] = '1.2.3.4';
        $this->assertSame('1.2.3.4', $ipDetector->getRequestIp());
        unset($_SERVER['HTTP_CLIENT_IP']);

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $this->assertSame('1.2.3.5', $ipDetector->getRequestIp());
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $_SERVER['REMOTE_ADDR'] = '1.2.3.6';
        $this->assertSame('1.2.3.6', $ipDetector->getRequestIp());
        unset($_SERVER['REMOTE_ADDR']);
    }
}
