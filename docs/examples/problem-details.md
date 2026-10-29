---
title: 'Example: Problem Details'
description: 'Runnable Nette API example: RFC 9457 application/problem+json for 400, 401, 404, 409, 429 and 500, production mode, Accept negotiation and the OpenAPI ProblemDetails schema.'
---

# Example: Problem Details

Every error of an orders API as [RFC 9457 problem details](/guide/problem-details), in development, in production and only for clients that ask for them.

<ProjectSource name="problem-details" :files="['config/common.neon', 'config/negotiate.neon', 'app/Api/OrderCreateHandler.php', 'app/ExhaustedRateLimit.php', 'e2e.sh']" />

::: tip Since 4.0.0
`ProblemDetailsErrorHandler`, `NegotiatingErrorHandler` and `ProblemDetailsResponse` are new in 4.0.0.
:::

| Request | Status | `type` | Note |
| --- | --- | --- | --- |
| `POST /api/v1/orders` `product=book&quantity=2` | `201` | | `application/json` |
| `POST /api/v1/orders` `quantity=9` | `400` | `validation-error` | `errors` for `product` and `quantity`, in production too |
| `POST /api/v1/orders` `product=sold-out&quantity=2` | `409` | `out-of-stock` | returned by the handler, with `product` and `available` |
| `POST /api/v1/orders` `product=crash&quantity=1` | `500` | `internal-error` | the message only in development, always in `log/exception.log` |
| `GET /api/v1/me` without a token | `401` | `unauthorized` | |
| `GET /api/v1/limited` | `429` | `rate-limited` | `limit`, `retryAfter` and `Retry-After: 30` |
| `GET /api/v1/nothing` | `404` | `not-found` | |
| `GET /api/v1/openapi` | `200` | | `ProblemDetails` schema for the errors |

With `ERRORS=negotiate` the errors keep the 3.x format unless the client sends `Accept: application/problem+json`.

## The services

<<< @/../examples/problem-details/config/common.neon{yaml}

## Negotiation

<<< @/../examples/problem-details/config/negotiate.neon{yaml}

<<< @/../examples/problem-details/app/Bootstrap.php

## The handler

<<< @/../examples/problem-details/app/Api/OrderCreateHandler.php

## The rate limit

<<< @/../examples/problem-details/app/ExhaustedRateLimit.php

## The test

<<< @/../examples/problem-details/e2e.sh{bash}

## FAQ

### How do I run it?

`composer install` and `./e2e.sh` in `examples/problem-details`. The test starts the PHP built-in server on ports 8091, 8092 and 8093.

### Why does the 409 need an output?

Outputs are validated in development, and a response whose code is not in `outputs()` becomes a `500` from `handleSchema()`. The handler declares `409` with `ResponseBuilder::problemDetailsSchema()`.

### Why is the type a URL that does not exist?

`api.example.com` is a placeholder. Point `problemTypeBaseUri` to pages that document your problems.

### How do I keep the old format for old clients?

Use `config/negotiate.neon`: `NegotiatingErrorHandler` sends problem details only to clients with `Accept: application/problem+json`.
