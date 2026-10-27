---
title: 'Example: rate limit'
description: 'Runnable Nette API example with your own RateLimitInterface: three requests per hour per API key, X-RateLimit headers, Retry-After and a custom 429 body.'
---

# Example: rate limit

`GET /api/v1/quote` allows three requests per hour per API key. The fourth answers `429` with `Retry-After`.

<ProjectSource name="rate-limit" :files="['config/common.neon', 'app/FileRateLimit.php', 'app/Api/QuoteHandler.php', 'e2e.sh']" />

<Diagram name="examples-rate-limit-1" alt="FileRateLimit counts requests per API key in a file; while requests remain the presenter adds X-RateLimit headers and continues to authorization; at zero it answers 429 with Retry-After" />

| Request | Response |
| --- | --- |
| 1st to 3rd with `X-Api-Key: key-a` | `200`, `X-RateLimit-Remaining` 3, 2, 1 |
| 4th with `key-a` | `429 Slow down`, `X-RateLimit-Remaining: 0` and `Retry-After` |
| 1st with `key-b` | `200`, its own counter, `Remaining: 3` |
| a wrong key | rate limit headers, then `401` |

## The rate limit

`check()` returns a `RateLimitResponse(limit, remaining, retryAfter, errorResponse)`. The presenter answers `429` when `remaining` is `0`.

<<< @/../examples/rate-limit/app/FileRateLimit.php

## The services

The rate limit is the fourth argument of `addApi()`.

<<< @/../examples/rate-limit/config/common.neon{yaml}

## The handler

<<< @/../examples/rate-limit/app/Api/QuoteHandler.php

## The test

<<< @/../examples/rate-limit/e2e.sh{bash}

## FAQ

### Why is a wrong key counted?

The rate limit runs before authorization, so a client guessing keys is limited too. See [ApiPresenter pipeline](/guide/presenter).

### Why a file and flock()?

So the example has no dependencies. In production use Redis with `INCR` and `EXPIRE`, or your database.

### What if I return no error response?

The presenter sends `429` with `Too many requests. Retry after N seconds.`. See [Rate limiting](/guide/rate-limit).

### Which headers does the client get?

`X-RateLimit-Limit`, `X-RateLimit-Remaining` and, at the limit, `Retry-After`.

### Can I limit by IP?

Yes, the example falls back to the IP when there is no key. Use an IP detector that cannot be spoofed, see [Authorization](/guide/authorization#tokens-and-ip-restrictions).
