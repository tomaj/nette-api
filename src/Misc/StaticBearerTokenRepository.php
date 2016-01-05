<?php

namespace Tomaj\NetteApi\Misc;

use Exception;

class StaticBearerTokenRepository implements BearerTokenRepositoryInterface
{
    /**
     * array
     */
    private $validTokens = [];

    /**
     * Create static bearer token repository.
     * You can pass multiple tokens that will be available for your api.
     * Format is associtive array where key is token string and value is IP range
     *
     * Example:
     * ['ef0p9iwehjgoihrgrsdgfoihw4t' => '*']
     *
     * Or:
     * ['asfoihegoihregoihrhgrehg' => '127.0.0.1', 'asfo9uyewtoiyewgt4ty4r' => '*']
     *
     * @see BearerTokenAuthorization#isValidIp for all available Ip range formats
     *
     * @param array $validTokens
     */
    public function __construct($validTokens = [])
    {
        $this->validTokens = $validTokens;
    }

    /**
     * {@inheritdoc}
     */
    public function validToken($token)
    {
        return in_array($token, array_keys($this->validTokens));
    }

    /**
     * {@inheritdoc}
     */
    public function ipRestrictions($token)
    {
        if (isset($this->validTokens[$token])) {
            return $this->validTokens[$token];
        }
        throw new Exception('Token not found - this should never happend because token validation is before IP validation');
    }
}
