<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Nette\Http\IRequest;

class BasicAuthentication implements ApiAuthorizationInterface
{
    /** @var array */
    private $authentications;

    /** @var IRequest */
    private $httpRequest;

    /**
     * @param array<string, string> $autentications - available username - password pairs
     * @param IRequest $httpRequest
     */
    public function __construct(array $autentications, IRequest $httpRequest)
    {
        $this->authentications = $autentications;
        $this->httpRequest = $httpRequest;
    }

    /**
     * {@inheritdoc}
     */
    public function authorized(): bool
    {
        $urlScript = $this->httpRequest->getUrl();
        $authentication = $this->authentications[$urlScript->getUser()] ?? null;
        if (!$authentication) {
            return false;
        }
        return $authentication === $urlScript->getPassword();
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage(): ?string
    {
        return 'Incorrect username or password';
    }
}
