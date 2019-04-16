<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

class IpDetector implements IpDetectorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getRequestIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] !== '127.0.0.1') {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = 'cli';
        }
        return $ip;
    }
}
