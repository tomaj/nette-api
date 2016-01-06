<?php

namespace Tomaj\NetteApi\Authorization;

use Tomaj\NetteApi\Misc\BearerTokenRepositoryInterface;
use Tomaj\NetteApi\Misc\IpDetectorInterface;

class BearerTokenAuthorization implements ApiAuthorizationInterface
{
    /**
     * @var BearerTokenRepositoryInterface
     */
    private $tokenRepository;

    /**
     * @var string|boolean
     */
    private $errorMessage = false;

    /**
     * @var IpDetectorInterface
     */
    private $ipDetector;

    /**
     * BearerTokenAuthorization constructor.
     *
     * @param BearerTokenRepositoryInterface $tokenRepository
     * @param IpDetectorInterface            $ipDetector
     */
    public function __construct(BearerTokenRepositoryInterface $tokenRepository, IpDetectorInterface $ipDetector)
    {
        $this->tokenRepository = $tokenRepository;
        $this->ipDetector = $ipDetector;
    }

    /**
     * {@inheritdoc}
     */
    public function authorized()
    {
        $token = $this->readAuthorizationToken();
        if (!$token) {
            return false;
        }

        $result = $this->tokenRepository->validToken($token);
        if (!$result) {
            $this->errorMessage = 'Token doesn\'t exists or isn\'t active';
            return false;
        }

        if (!$this->isValidIp($this->tokenRepository->ipRestrictions($token))) {
            $this->errorMessage = 'Invalid IP';
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Check if actual IP from detector satisfies @ipRestristions
     * $ipRestrictions should contains multiple formats:
     *   '*'                  - accessible from anywhare
     *   '127.0.0.1'          - accessible from single IP
     *   '127.0.0.1,127.0.02' - accessible from multiple IP, separator could be new line or space
     *   '127.0.0.1/32'       - accessible from ip range
     *
     * @return boolean
     */
    private function isValidIp($ipRestrictions)
    {
        if ($ipRestrictions == '*' || $ipRestrictions == '' || $ipRestrictions == null) {
            return true;
        }
        $ip = $this->ipDetector->getRequestIp();

        $ipWhiteList = str_replace([',', ' ', "\n"], '#', $ipRestrictions);
        $ipWhiteList = explode('#', $ipWhiteList);
        foreach ($ipWhiteList as $whiteIp) {
            if ($whiteIp == $ip) {
                return true;
            }
            if (strpos($whiteIp, '/') !== false) {
                return $this->ipInRange($ip, $whiteIp);
            }
        }

        return false;
    }

    /**
     * Check if IP is in $range
     *
     * @param string $ip
     * @param string $range
     * @return boolean
     */
    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') == false) {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        list( $range, $netmask ) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
    }


    /**
     * Read HTTP reader with authorization token
     * If everything is ok, it return token. In other situations returns false and set errorMessage.
     *
     * @return string|boolean
     */
    private function readAuthorizationToken()
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->errorMessage = 'Authorization header HTTP_Authorization is not set';
            return false;
        }
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) != 2) {
            $this->errorMessage = 'Authorization header contains invalid structure';
            return false;
        }
        if (!strtolower($parts[0]) == 'bearer') {
            $this->errorMessage = 'Authorization header doesn\'t contains bearer token';
            return false;
        }
        return $parts[1];
    }
}
