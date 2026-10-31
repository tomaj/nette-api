<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

use Tomaj\NetteApi\RateLimit\RateLimitContext;

/**
 * One quota per caller, checked after a successful authorization (short name identity).
 *
 * The identity comes from an authorization implementing Tomaj\NetteApi\Authorization\AuthorizationIdentityInterface (the token or API key of
 * the token authorizations, the user name of BasicAuthentication). It is hashed, so no token is stored in the storage.
 * Without an identity the limit is skipped.
 */
final readonly class IdentityKey implements RateLimitKeyInterface
{
    #[\Override]
    public function key(RateLimitContext $context): ?string
    {
        return $context->identity === null ? null : 'identity:' . hash('sha256', $context->identity);
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return true;
    }
}
