<?php

namespace Tomaj\NetteApi\Misc;

class StaticIpDetector implements IpDetectorInterface
{
	/**
	 * @var stdring
	 */
	private $ip;

	/**
	 * Create Static Ip Detector
	 * Ip that will be in constructor will return as actual request IP.
	 * 
	 * @param string $ip
	 */
	public function __construct($ip)
	{
		$this->ip = $ip;
	}

	/**
     * {@inheritdoc}
     */
    public function getRequestIp()
    {
        return $this->ip;
    }
}
