<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\TokenRepositoryInterface;

class BearerTokenAuthorization extends TokenAuthorization
{
   /**
     * BearerTokenAuthorization constructor.
     *
     * @param TokenRepositoryInterface $tokenRepository
     * @param IpDetectorInterface      $ipDetector
     */
    public function __construct(TokenRepositoryInterface $tokenRepository, IpDetectorInterface $ipDetector)
    {
        parent::__construct($tokenRepository, $ipDetector);
    }

    /**
     * Read HTTP reader with authorization token
     * If everything is ok, it return token. In other situations returns false and set errorMessage.
     *
     * @return string|null
     */
    protected function readAuthorizationToken(): ?string
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
            $this->errorMessage = 'Authorization header doesn\'t contain bearer token';
            return null;
        }
        return $parts[1];
    }
}
