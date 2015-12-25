<?php

namespace Tomaj\NetteApi\Logger;

interface ApiLoggerInterface
{
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
