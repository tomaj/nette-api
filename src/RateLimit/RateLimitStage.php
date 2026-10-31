<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\RateLimit;

enum RateLimitStage
{
    /** before the authorization, the identity is not known yet (limits by IP or endpoint) */
    case BeforeAuthorization;

    /** after a successful authorization, the identity is known (limits by identity) */
    case AfterAuthorization;
}
