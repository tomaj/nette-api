<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Key;

use Tomaj\NetteApi\RateLimit\RateLimitContext;

/**
 * A quota per combination of keys, for example identity + endpoint (in neon key: [identity, endpoint]).
 * It needs the identity when any part does; when any part has no key, the limit is skipped.
 */
final readonly class CompositeKey implements RateLimitKeyInterface
{
    /** @var array<RateLimitKeyInterface> */
    private array $keys;

    public function __construct(RateLimitKeyInterface ...$keys)
    {
        if ($keys === []) {
            throw new \InvalidArgumentException('CompositeKey needs at least one key, for example new CompositeKey(new IdentityKey(), new EndpointKey()).');
        }
        $this->keys = $keys;
    }

    #[\Override]
    public function key(RateLimitContext $context): ?string
    {
        $parts = [];
        foreach ($this->keys as $key) {
            $part = $key->key($context);
            if ($part === null) {
                return null;
            }
            // escaped, so a '|' in an own key cannot make two different lists of parts give the same key
            $parts[] = strtr($part, ['\\' => '\\\\', '|' => '\\|']);
        }

        return implode('|', $parts);
    }

    #[\Override]
    public function needsIdentity(): bool
    {
        return array_any($this->keys, fn($key): bool => $key->needsIdentity());
    }
}
