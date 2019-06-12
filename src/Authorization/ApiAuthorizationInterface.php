<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

interface ApiAuthorizationInterface
{
    /**
     * Main method to check if this authorization authorize actual request.
     *
     * @return boolean
     */
    public function authorized(): bool;

    /**
     * If authorization deny acces, this method should provide additional information
     * abount cause of restriction.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;
}
