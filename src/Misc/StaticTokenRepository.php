<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

/**
 * @todo change implements to TokenRepositoryInterface after BearerTokenRepositoryInterface will be removed in 3.0.0
 */
class StaticTokenRepository implements BearerTokenRepositoryInterface
{
    /**
     *
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
     * @param array<string, string> $validTokens Array of valid tokens as keys and optional IP restrictions as values
     */
    public function __construct(private array $validTokens = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validToken(string $token): bool
    {
        return in_array($token, array_keys($this->validTokens), true);
    }

    /**
     * {@inheritdoc}
     */
    public function ipRestrictions(string $token): ?string
    {
        if (isset($this->validTokens[$token])) {
            return $this->validTokens[$token];
        }
        return null;
    }
}
