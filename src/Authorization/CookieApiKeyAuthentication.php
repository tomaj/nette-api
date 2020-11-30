<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\TokenRepositoryInterface;

class CookieApiKeyAuthentication extends TokenAuthorization
{
    private $cookieName;

    public function __construct(string $cookieName, TokenRepositoryInterface $tokenRepository, IpDetectorInterface $ipDetector)
    {
        parent::__construct($tokenRepository, $ipDetector);
        $this->cookieName = $cookieName;
    }

    protected function readAuthorizationToken(): ?string
    {
        $apiKey = $_COOKIE[$this->cookieName] ?? null;
        if (!$apiKey) {
            $this->errorMessage = 'API key is not set';
            return null;
        }
        return $apiKey;
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
