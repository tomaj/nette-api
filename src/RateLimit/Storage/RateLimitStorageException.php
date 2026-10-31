<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit\Storage;

/**
 * The storage is not available (Redis is down, the cache directory is not writable, ...)
 */
final class RateLimitStorageException extends \RuntimeException
{
}
