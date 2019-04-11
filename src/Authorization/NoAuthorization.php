<?php

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
