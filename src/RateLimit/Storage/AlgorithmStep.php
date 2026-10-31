<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

/**
 * The decision of a rate limit algorithm and the state to keep for the next request
 */
final readonly class AlgorithmStep
{
    /**
     * @param mixed $state saved for the next step(), null to keep the previous state
     * @param int $ttl seconds after which the state is not needed and may be removed, at least 1 with a state
     */
    public function __construct(
        public StorageResult $result,
        public mixed $state = null,
        public int $ttl = 0,
    ) {
        if ($state !== null && $ttl < 1) {
            throw new \InvalidArgumentException(sprintf(
                'A rate limit algorithm returned a state with the ttl %d, but the storage needs at least 1 second to keep it. Return the seconds after which the state is not needed, for example new AlgorithmStep($result, $state, $interval + 1).',
                $ttl,
            ));
        }
    }
}
