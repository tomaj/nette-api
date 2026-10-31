<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI\Fixtures;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface;

final class IdentityAuthorization implements ApiAuthorizationInterface, AuthorizationIdentityInterface
{
    #[\Override]
    public function authorized(): bool
    {
        return true;
    }

    #[\Override]
    public function getErrorMessage(): ?string
    {
        return null;
    }

    #[\Override]
    public function getIdentity(): ?string
    {
        return 'user';
    }
}
