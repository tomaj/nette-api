<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

/**
 * @todo change implements to TokenRepositoryInterface after BearerTokenRepositoryInterface will be removed in 3.0.0
 */
class StaticTokenRepository implements BearerTokenRepositoryInterface
{
    /**
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
