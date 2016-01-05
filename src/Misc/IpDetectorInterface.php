<?php

namespace Tomaj\NetteApi\Misc;

interface IpDetectorInterface
{
	/**
	 * Get actual request IP.
	 *
	 * @return string
	 */
    public function getRequestIp();
}
