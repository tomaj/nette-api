<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

final readonly class StaticIpDetector implements IpDetectorInterface
{
    /**
     * Create Static Ip Detector
     * Ip that will be in constructor will return as actual request IP.
     */
    public function __construct(private string $ip)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getRequestIp(): string
    {
        return $this->ip;
    }
}
