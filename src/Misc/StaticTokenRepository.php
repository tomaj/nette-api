<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

final class StaticTokenRepository implements TokenRepositoryInterface
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
     * @param array<array-key, string> $validTokens Array of valid tokens as keys and optional IP restrictions as values
     */
    public function __construct(private array $validTokens = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function validToken(string $token): bool
    {
        // constant-time comparison; every key is checked so the timing does not reveal which token matched
        // integer keys (numeric tokens cast by PHP) never matched the strict in_array(), keep that outcome
        $valid = false;
        foreach (array_keys($this->validTokens) as $validToken) {
            if (is_string($validToken) && hash_equals($validToken, $token)) {
                $valid = true;
            }
        }

        return $valid;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function ipRestrictions(string $token): ?string
    {
        return $this->validTokens[$token] ?? null;
    }
}
