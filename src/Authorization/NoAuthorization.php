<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

final class NoAuthorization implements ApiAuthorizationInterface
{
    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function authorized(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getErrorMessage(): ?string
    {
        return null;
    }
}
