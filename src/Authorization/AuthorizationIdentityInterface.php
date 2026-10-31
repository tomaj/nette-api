<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

/**
 * An authorization that knows who called, used by the identity rate limit key (IdentityKey).
 *
 * Built-in: the token authorizations (BearerTokenAuthorization, HeaderApiKeyAuthentication, QueryApiKeyAuthentication,
 * CookieApiKeyAuthentication) return the token, BasicAuthentication returns the user name.
 */
interface AuthorizationIdentityInterface
{
    /**
     * The caller after a successful authorized(), null before it or after a failed one.
     * Any stable string works (a user id, a token); the rate limit hashes it before storing.
     */
    public function getIdentity(): ?string;
}
