<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Attribute;

/**
 * Rate limit of all endpoints of the handler class. Without it, api.defaults.rateLimit applies.
 *
 *     #[RateLimit('perIp')]                          a name from api.rateLimits, '@service' or a class name
 *     #[RateLimit(['perIp', 'perUser'])]             more limits, all of them are counted
 *     #[RateLimit(false)]                            no rate limit
 *     #[RateLimit(limit: 60, interval: '1 minute')]  an inline limit, each endpoint of the class has its own counters
 *     #[RateLimit(limit: 1000, interval: '1 hour', algorithm: 'tokenBucket', key: 'identity')]
 *
 * Inline: algorithm is fixedWindow, slidingWindow (default), tokenBucket or a class implementing RateLimitAlgorithmInterface; key is ip (default), identity, endpoint,
 * a class implementing RateLimitKeyInterface, or a list of them; name is the policy name in the RateLimit headers.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class RateLimit
{
    /** @var string|list<string>|array{algorithm: string, limit: int, interval: int|string, key: string|list<string>, name: string|null}|false */
    public string|array|false $rateLimit;

    /**
     * @param string|list<string>|false|null $rateLimit
     * @param string|list<string> $key
     */
    public function __construct(
        string|array|false|null $rateLimit = null,
        ?int $limit = null,
        int|string|null $interval = null,
        string $algorithm = 'slidingWindow',
        string|array $key = 'ip',
        ?string $name = null,
    ) {
        if ($rateLimit !== null) {
            $this->rateLimit = $limit === null
                ? $rateLimit
                : throw new \InvalidArgumentException("#[RateLimit] has both a rate limit name and an inline limit. Use either #[RateLimit('perIp')] or #[RateLimit(limit: 60, interval: '1 minute')].");
        } elseif ($limit === null || $interval === null) {
            throw new \InvalidArgumentException("#[RateLimit] needs a name from api.rateLimits or limit and interval, for example #[RateLimit('perIp')] or #[RateLimit(limit: 60, interval: '1 minute')].");
        } else {
            $this->rateLimit = ['algorithm' => $algorithm, 'limit' => $limit, 'interval' => (string) $interval, 'key' => $key, 'name' => $name];
        }
    }
}
