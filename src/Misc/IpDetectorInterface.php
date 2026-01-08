<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

interface IpDetectorInterface
{
    /**
     * Get actual request IP.
     */
    public function getRequestIp(): string;
}
