<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\TokenRepositoryInterface;

class HeaderApiKeyAuthentication extends TokenAuthorization
{
    private $headerName;

    public function __construct(string $headerName, TokenRepositoryInterface $tokenRepository, IpDetectorInterface $ipDetector)
    {
        parent::__construct($tokenRepository, $ipDetector);
        $this->headerName = $headerName;
    }

    protected function readAuthorizationToken(): ?string
    {
        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', $this->headerName));
        $apiKey = $_SERVER[$headerName] ?? null;
        if (!$apiKey) {
            $this->errorMessage = 'API key is not set';
            return null;
        }
        return $apiKey;
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
