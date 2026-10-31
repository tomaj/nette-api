<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;

/**
 * One quota per client IP from IpDetectorInterface, checked before the authorization (short name ip).
 *
 * The key is only as reliable as the IP detector: the default IpDetector trusts the Client-IP and X-Forwarded-For
 * headers, so a client that can set them gets a new quota with every value. Behind no proxy, or with an untrusted one,
 * configure api.ipDetector to read only REMOTE_ADDR or the address set by your proxy.
 * Without any IpDetectorInterface service (only possible without the DI extension) the key throws a LogicException.
 */
final readonly class IpKey implements RateLimitKeyInterface
{
    public const int MAX_LENGTH = 45;

    #[\Override]
    public function key(RateLimitContext $context): ?string
    {
        if (!$context->hasIpDetector) {
            // without a detector every IP limit would be skipped silently
            $endpoint = $context->api->getEndpoint();
            throw new \LogicException(sprintf(
                'The rate limit of endpoint %s %s is keyed by ip, but the container has no %s service, so the client IP is not known. '
                . 'Register one, for example services: - %s (or your own detector reading the address set by your proxy), or use another key.',
                $endpoint->getMethod(),
                $endpoint->getUrl(),
                IpDetectorInterface::class,
                IpDetector::class,
            ));
        }
        if ($context->ip === null || $context->ip === '') {
            return null;
        }
        // an IP is at most 45 characters; a longer value (a list from X-Forwarded-For, a forged header) is hashed to keep keys short
        return 'ip:' . (strlen($context->ip) > self::MAX_LENGTH ? hash('sha256', $context->ip) : $context->ip);
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return false;
    }
}
