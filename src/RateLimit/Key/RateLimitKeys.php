<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

/**
 * Builds a key from its short name: ip, identity, endpoint, or a list of them like [identity, endpoint]
 */
final class RateLimitKeys
{
    public const array NAMES = ['ip' => IpKey::class, 'identity' => IdentityKey::class, 'endpoint' => EndpointKey::class];

    /**
     * @param string|array<mixed> $key a name, a class implementing RateLimitKeyInterface, or a list of them
     * @throws \InvalidArgumentException for an unknown name or an empty list
     */
    public static function fromName(string|array $key, string $where = 'the rate limit key'): RateLimitKeyInterface
    {
        if ($key === []) {
            throw new \InvalidArgumentException(sprintf('%s: the list of keys is empty. Use ip, identity, endpoint, or a list like [identity, endpoint].', $where));
        }
        $keys = [];
        foreach ((array) $key as $name) {
            if (is_string($name) && !isset(self::NAMES[$name]) && is_a($name, RateLimitKeyInterface::class, true)) {
                try {
                    $keys[] = new $name();
                } catch (\Error $e) {
                    throw new \InvalidArgumentException(sprintf(
                        '%s: the key class %s cannot be created without constructor arguments: %s. Remove the arguments, or create the limiter as a service with new FixedWindowLimiter(..., new %s($argument), $storage).',
                        $where,
                        $name,
                        $e->getMessage(),
                        $name,
                    ), previous: $e);
                }
                continue;
            }
            $class = (is_string($name) ? self::NAMES[$name] ?? null : null) ?? throw new \InvalidArgumentException(sprintf(
                "%s: unknown key '%s'. Use ip (one quota per client IP), identity (per caller, after the authorization), endpoint (one quota per endpoint shared by all callers), or a list like [identity, endpoint]; for your own key use a class implementing %s (without constructor arguments).",
                $where,
                is_scalar($name) ? $name : get_debug_type($name),
                RateLimitKeyInterface::class,
            ));
            $keys[] = new $class();
        }

        return count($keys) === 1 ? $keys[0] : new CompositeKey(...$keys);
    }
}
