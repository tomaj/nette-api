---
title: 'Example: authorization and rate limit'
description: 'Runnable Nette API example: bearer token, X-API-Key header, HTTP Basic with hash_equals, IP restrictions that cannot be spoofed and a 3 per minute rate limit.'
---

# Example: authorization and rate limit

Four endpoints with the same handler, each protected differently.

<ProjectSource name="auth" :files="['config/common.neon', 'app/BasicCredentialsAuthorization.php', 'app/RemoteAddrIpDetector.php', 'app/FileRateLimit.php', 'e2e.sh']" />

<Diagram name="examples-auth-1" alt="Four endpoints: bearer, api-key and basic check credentials and answer 401 when wrong, limited answers 429 after three requests" />

| Endpoint | Protection | Try |
| --- | --- | --- |
| `/api/v1/bearer` | `BearerTokenAuthorization` | `-H 'Authorization: Bearer secret-token'` |
| `/api/v1/api-key` | `HeaderApiKeyAuthentication` | `-H 'X-API-Key: secret-token'` |
| `/api/v1/basic` | `BasicCredentialsAuthorization` from the example | `-u admin:change-me` |
| `/api/v1/limited` | `NoAuthorization` and `FileRateLimit` | four calls in a minute |

## The services

<<< @/../examples/auth/config/common.neon{yaml}

`office-token` is allowed only from `10.0.0.0/8`; the test shows that a forged `X-Forwarded-For` does not help.

## The client IP

<<< @/../examples/auth/app/RemoteAddrIpDetector.php

The bundled `IpDetector` trusts client headers. This one reads only `REMOTE_ADDR`. See [Authorization](/guide/authorization#tokens-and-ip-restrictions).

## HTTP Basic

<<< @/../examples/auth/app/BasicCredentialsAuthorization.php

In 3.x the bundled `BasicAuthentication` does not work with `nette/http` 3.2 and newer (4.0 fixes it); this class reads the `Authorization` header and compares with `hash_equals()`.

## Rate limit

<<< @/../examples/auth/app/FileRateLimit.php

## The test

<<< @/../examples/auth/e2e.sh{bash}

## FAQ

### Where do real tokens come from?

From your own `TokenRepositoryInterface` backed by a database. `StaticTokenRepository` with tokens in NEON is for examples and internal tools.

### Why is the rate limit not autowired?

Nette DI would pass it to every `addApi()` call. See [Rate limiting](/guide/rate-limit).

### What do the 401 messages say?

For example `Token doesn't exists or isn't active` or `Invalid IP`. See [Authorization](/guide/authorization).

### Is the handler different per endpoint?

No, `WhoAmIHandler` returns the endpoint it was called for, so the test can see which one ran.

### Can I try these in the web console?

The [OpenAPI and console example](/examples/openapi-console) has a bearer protected endpoint in the console.
