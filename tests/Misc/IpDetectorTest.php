<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\IpDetector;

class IpDetectorTest extends TestCase
{
    public function testValidation()
    {
        $ipDetector = new IpDetector();
        $this->assertEquals('cli', $ipDetector->getRequestIp());

        $_SERVER['HTTP_CLIENT_IP'] = '1.2.3.4';
        $this->assertEquals('1.2.3.4', $ipDetector->getRequestIp());
        unset($_SERVER['HTTP_CLIENT_IP']);

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.5';
        $this->assertEquals('1.2.3.5', $ipDetector->getRequestIp());
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $_SERVER['REMOTE_ADDR'] = '1.2.3.6';
        $this->assertEquals('1.2.3.6', $ipDetector->getRequestIp());
        unset($_SERVER['REMOTE_ADDR']);
    }
}
