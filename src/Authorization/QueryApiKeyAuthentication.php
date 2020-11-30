<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Misc\TokenRepositoryInterface;

class QueryApiKeyAuthentication extends TokenAuthorization
{
    private $queryParamName;

    public function __construct(string $queryParamName, TokenRepositoryInterface $tokenRepository, IpDetectorInterface $ipDetector)
    {
        parent::__construct($tokenRepository, $ipDetector);
        $this->queryParamName = $queryParamName;
    }

    protected function readAuthorizationToken(): ?string
    {
        $apiKey = $_GET[$this->queryParamName] ?? null;
        if (!$apiKey) {
            $this->errorMessage = 'API key is not set';
            return null;
        }
        return $apiKey;
    }

    public function getQueryParamName(): string
    {
        return $this->queryParamName;
    }
}
