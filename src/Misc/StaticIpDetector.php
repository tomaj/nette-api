<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

class StaticIpDetector implements IpDetectorInterface
{
    /**
     * Create Static Ip Detector
     * Ip that will be in constructor will return as actual request IP.
     */
    public function __construct(private readonly string $ip)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestIp(): string
    {
        return $this->ip;
    }
}
