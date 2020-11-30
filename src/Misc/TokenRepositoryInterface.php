<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

interface TokenRepositoryInterface
{
    /**
     * Return true if token is valid, otherwise return false
     *
     * @param string $token
     * @return bool
     */
    public function validToken(string $token): bool;

    /**
     * Return ip mask
     *
     * Examples:
     *   '*' - all access
     *   '152.26.252.142' - access only from this ip
     *   '156.26.252/32'  - access from ip range
     *   false            - if token doesn't exists
     *
     * @param string $token
     *
     * @return string|null
     */
    public function ipRestrictions(string $token): ?string;
}
