<?php

namespace Tomaj\NetteApi\Logger;

interface ApiLoggerInterface
{
    /**
     * Log processed api request
     *
     * @param integer   $responseCode
     * @param string    $requestMethod
     * @param string    $requestHeader
     * @param string    $requestUri
     * @param string    $requestIp
     * @param string    $requestAgent
     * @param integer   $responseTime
     * @return boolean
     */
    public function log(
        $responseCode,
        $requestMethod,
        $requestHeader,
        $requestUri,
        $requestIp,
        $requestAgent,
        $responseTime
    );
}
