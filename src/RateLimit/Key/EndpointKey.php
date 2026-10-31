<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

use Tomaj\NetteApi\RateLimit\RateLimitContext;

/**
 * One quota per endpoint (method, version, package and action) shared by all callers, checked before the authorization (short name endpoint).
 * Combine it with ip or identity for a quota per caller and endpoint.
 */
final readonly class EndpointKey implements RateLimitKeyInterface
{
    #[\Override]
    public function key(RateLimitContext $context): string
    {
        $endpoint = $context->api->getEndpoint();
        // encoded, so a ':' in a package or an action cannot make two endpoints share a key
        return 'endpoint:' . implode(':', array_map(rawurlencode(...), [$endpoint->getMethod(), $endpoint->getVersion(), $endpoint->getPackage(), $endpoint->getApiAction() ?? '']));
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return false;
    }
}
