<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

/**
 * Runs a RateLimitInterface of 3.x as a RateLimiterInterface, before the authorization like in 3.x.
 * remaining 0 answers 429 with Retry-After and the own error response of RateLimitResponse.
 *
 * @deprecated since 4.0, removed in 5.0 together with RateLimitInterface
 */
final readonly class LegacyRateLimitAdapter implements RateLimiterInterface
{
    public const string POLICY = 'default';

    public function __construct(private RateLimitInterface $rateLimit)
    {
        @trigger_error(self::deprecation($rateLimit::class), E_USER_DEPRECATED);
    }

    /**
     * A RateLimiterInterface as it is, a RateLimitInterface of 3.x wrapped in the adapter
     */
    public static function wrap(RateLimiterInterface|RateLimitInterface $rateLimit): RateLimiterInterface
    {
        return $rateLimit instanceof RateLimiterInterface ? $rateLimit : new self($rateLimit);
    }

    /**
     * @infection-ignore-all the cost is not used
     */
    #[\Override]
    public function consume(RateLimitContext $context, int $cost = 1): array
    {
        if ($context->stage !== RateLimitStage::BeforeAuthorization) {
            return [];
        }
        $response = $this->rateLimit->check();
        if ($response === null) {
            return [];
        }

        return [new RateLimitResult(
            $response->getRemaining() !== 0,
            $response->getLimit(),
            $response->getRemaining(),
            0,
            $response->getRetryAfter() ?? 0,
            $response->getRetryAfter(),
            self::POLICY,
            $response->getErrorResponse(),
        )];
    }

    public function getRateLimit(): RateLimitInterface
    {
        return $this->rateLimit;
    }

    public static function deprecation(string $class): string
    {
        return sprintf(
            '%s implements %s, which is deprecated since nette-api 4.0 and will be removed in 5.0; it still works through %s. '
            . 'Replace it with a built-in limiter in neon, for example api: {rateLimits: {perIp: {algorithm: slidingWindow, limit: 60, interval: 1 minute, key: ip}}}, '
            . 'or implement %s::consume(RateLimitContext $context, int $cost = 1): array returning a list of RateLimitResult. See UPGRADE-4.0.md, section Rate limiting.',
            $class,
            RateLimitInterface::class,
            self::class,
            RateLimiterInterface::class,
        );
    }
}
