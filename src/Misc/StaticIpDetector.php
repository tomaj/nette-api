<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

class StaticIpDetector implements IpDetectorInterface
{
    /** @var string */
    private $ip;

    /**
     * Create Static Ip Detector
     * Ip that will be in constructor will return as actual request IP.
     *
     * @param string $ip
     */
    public function __construct(string $ip)
    {
        $this->ip = $ip;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestIp(): string
    {
        return $this->ip;
    }
}
