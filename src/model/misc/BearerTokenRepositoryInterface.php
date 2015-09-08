<?php

namespace Tomaj\NetteApi\Misc;

interface BearerTokenRepositoryInterface
{
    /**
     * Return true if token is valid, otherwise return false
     *
     * @param $token string
     * @return bool
     */
    public function validToken($token);

    /**
     * Return ip mask
     *
     * Examples:
     *   '*' - all access
     *   '152.26.252.142' - access only from this ip
     *   '156.26.252/32' - access from ip range
     *
     * @param $token string
     * @return string
     */
    public function ipRestrictions($token);
}
