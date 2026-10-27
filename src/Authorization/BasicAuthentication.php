<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Nette\Http\IRequest;

class BasicAuthentication implements ApiAuthorizationInterface
{
    /**
     * @param array<array-key, mixed> $authentications - available username - password pairs (only string passwords can match)
     */
    public function __construct(private array $authentications, private readonly IRequest $httpRequest)
    {
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

        // non-string passwords (e.g. integers from neon) never matched with ===, keep that outcome
        return is_string($authentication) && hash_equals($authentication, $urlScript->getPassword());
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage(): ?string
    {
        return 'Incorrect username or password';
    }
}
