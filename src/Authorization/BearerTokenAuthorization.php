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
     * @var string|null
     */
    private $errorMessage = null;

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
    public function authorized(): bool
    {
        $token = $this->readAuthorizationToken();
        if ($token === null) {
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
    public function getErrorMessage(): ?string
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
     *   null                 - disabled access
     *
     * @return boolean
     */
    private function isValidIp(?string $ipRestrictions): bool
    {
        if ($ipRestrictions === null) {
            return false;
        }
        if ($ipRestrictions === '*' || $ipRestrictions === '') {
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
     * @param string $ip     this ip will be verified
     * @param string $range  is in IP/CIDR format eg 127.0.0.1/24
     * @return boolean
     */
    private function ipInRange(string $ip, string $range): bool
    {
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - (int)$netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return (($ipDecimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }

    /**
     * Read HTTP reader with authorization token
     * If everything is ok, it return token. In other situations returns false and set errorMessage.
     *
     * @return string|null
     */
    private function readAuthorizationToken(): ?string
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->errorMessage = 'Authorization header HTTP_Authorization is not set';
            return null;
        }
        $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
        if (count($parts) !== 2) {
            $this->errorMessage = 'Authorization header contains invalid structure';
            return null;
        }
        if (strtolower($parts[0]) !== 'bearer') {
            $this->errorMessage = 'Authorization header doesn\'t contains bearer token';
            return null;
        }
        return $parts[1];
    }
}
