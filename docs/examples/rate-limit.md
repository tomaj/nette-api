---
title: 'Example: rate limit'
description: 'Runnable Nette API example with the built-in rate limits: an app-wide default per IP, a per-key quota after authorization, an inline #[RateLimit] on a handler and an endpoint without a limit.'
---

# Example: rate limit

Every endpoint allows 100 requests per minute per IP. `GET /api/v1/quote` also allows three requests per hour per API key, `GET /api/v1/ping` two per minute from its attribute, and `GET /api/v1/status` has no limit.

<ProjectSource name="rate-limit" :files="['config/common.neon', 'app/Api/QuoteHandler.php', 'app/Api/Discovered/PingHandler.php', 'e2e.sh']" />

| Request | Response |
| --- | --- |
| 1st to 3rd `/quote` with `X-Api-Key: key-a` | `200`, `RateLimit: "perKey";r=2`, 1, 0 |
| 4th `/quote` with `key-a` | `429` with `Retry-After` |
| 1st `/quote` with `key-b` | `200`, its own quota, `r=2` |
| `/quote` with a wrong key | `401`, counted by `perIp` only |
| 3rd `/ping` | `429`, `Retry-After: 30` |
| any `/status` | `200`, no rate limit headers |

## The configuration

`rateLimits` defines the named policies, `defaults.rateLimit` applies `perIp` to every endpoint, `quote` adds `perKey` and `status` turns the limit off.

<<< @/../examples/rate-limit/config/common.neon{yaml}

## The handlers

<<< @/../examples/rate-limit/app/Api/QuoteHandler.php

`#[RateLimit]` on a discovered handler replaces the default with an inline limit:

<<< @/../examples/rate-limit/app/Api/Discovered/PingHandler.php

## The test

<<< @/../examples/rate-limit/e2e.sh{bash}

## FAQ

### Why is a wrong key counted?

`perIp` is keyed by the IP, so it runs before the authorization and a client guessing keys is limited too. `perKey` is keyed by `identity`, so it runs after a successful authorization. See [Rate limiting](/guide/rate-limit#before-and-after-authorization).

### Why does the third ping wait 30 seconds?

`tokenBucket` with 2 per minute refills one token every 30 seconds.

### Where are the counters?

In the Nette cache in `temp/cache`. With more servers use `storage: redis`, see [Storages](/guide/rate-limit#storages).

### Which headers does the client get?

`RateLimit-Policy` and `RateLimit` for every policy that applied, and `Retry-After` with `429`.

### Can I limit by IP behind a proxy?

Yes, with an IP detector that cannot be spoofed, see [Authorization](/guide/authorization#tokens-and-ip-restrictions).
