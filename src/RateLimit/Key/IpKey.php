<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

use Tomaj\NetteApi\Misc\IpDetector;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\RateLimit\RateLimitContext;

/**
 * One quota per client IP, checked before the authorization (short name ip).
 *
 * Behind a reverse proxy list it in http: proxy: of the Nette configuration, otherwise IpDetector gives the address
 * of the proxy and all clients share its quota.
 * An IPv6 client controls a whole subnet, so the quota is per /64; an IPv4-mapped IPv6 address counts as the IPv4 address.
 * Without any IpDetectorInterface service (only possible without the DI extension) the key throws a LogicException.
 */
final readonly class IpKey implements RateLimitKeyInterface
{
    public const int MAX_LENGTH = 45;

    #[\Override]
    public function key(RateLimitContext $context): ?string
    {
        if ($context->ip === null) {
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
        if ($context->ip === '') {
            return null;
        }
        // an IP is at most 45 characters; a longer value (a list from X-Forwarded-For, a forged header) is hashed to keep keys short
        return 'ip:' . (strlen($context->ip) > self::MAX_LENGTH ? hash('sha256', $context->ip) : self::normalize($context->ip));
    }

    private static function normalize(string $ip): string
    {
        $binary = @inet_pton($ip); // @ - a value of an own detector does not have to be an IP
        if ($binary === false || strlen($binary) !== 16) {
            return $ip;
        }
        if (str_starts_with($binary, str_repeat("\0", 10) . "\xFF\xFF")) {
            return (string) inet_ntop(substr($binary, 12));
        }

        return inet_ntop(substr($binary, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return false;
    }
}
