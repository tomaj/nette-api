---
title: Rate limiting
description: Limit how often a Nette API endpoint may be called with RateLimitInterface, RateLimitResponse, X-RateLimit headers and 429 Too Many Requests with Retry-After.
---

# Rate limiting

A rate limit is the optional fourth argument of `addApi()`. The library defines the interface and the response; how you count requests (Redis, a database, a file) is up to you.

<Diagram name="rate-limit-1" alt="ApiPresenter calls check(); remaining 0 answers 429 with Retry-After, otherwise the rate limit headers are added and the handler runs" />

## The interface

```php
interface RateLimitInterface
{
    public function check(): ?RateLimitResponse;
}
```

`check()` runs first, before params and authorization. Return `null` for "no limit here", or a `RateLimitResponse`:

```php
new RateLimitResponse(int $limit, int $remaining, ?int $retryAfter = null, ?IResponse $errorResponse = null)
```

| Result | Response |
| --- | --- |
| `null` | nothing added, the request continues |
| `remaining` > 0 | `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers, the request continues |
| `remaining` = 0 | `429`, the same headers plus `Retry-After`, and `{"status":"error","message":"Too many requests. Retry after N seconds."}` or your `$errorResponse`. A `null` `retryAfter` sends an empty `Retry-After` header and the message `Retry after  seconds.` without a number |

Endpoints without a rate limit get `NoRateLimit`, which returns `null`.

## Example

The [authorization example](/examples/auth) counts requests per IP in a file, 3 a minute:

<<< @/../examples/auth/app/FileRateLimit.php

Register it with `autowired: false`. Otherwise Nette DI autowires it as the fourth argument of every `addApi()` call in the setup:

```yaml
services:
	rateLimit:
		factory: App\FileRateLimit(directory: %tempDir%)
		autowired: false
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'limited'), App\Api\WhoAmIHandler, Tomaj\NetteApi\Authorization\NoAuthorization(), @rateLimit)
```

## FAQ

### Does the rate limit know which token called?

`check()` gets no arguments, because it runs before authorization. Inject `Nette\Http\IRequest` and read the header or the IP yourself.

### Why do all my endpoints suddenly have a rate limit?

The rate limit service is autowired into `addApi()`. Set `autowired: false`, as shown above.

### Are 429 responses logged?

No, in 3.x the presenter returns before the logger runs. See [Logging](/guide/logging).

### Can I return my own 429 body?

Yes, pass it as the fourth argument of `RateLimitResponse`.

### Which IP should I count by?

Use an IP detector that cannot be spoofed; see the warning in [Authorization](/guide/authorization#tokens-and-ip-restrictions).
