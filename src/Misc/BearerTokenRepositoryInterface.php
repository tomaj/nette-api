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
     *   '156.26.252/32'  - access from ip range
     *   false            - if token doesn't exists
     *
     * @param $token string
     *
     * @return string|false
     */
    public function ipRestrictions($token);
}
