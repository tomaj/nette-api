<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

interface IpDetectorInterface
{
    /**
     * Get actual request IP.
     *
     * @return string
     */
    public function getRequestIp(): string;
}
