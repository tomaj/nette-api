<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

class NoAuthorization implements ApiAuthorizationInterface
{
    /**
     * {@inheritdoc}
     */
    public function authorized(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage(): ?string
    {
        return null;
    }
}
