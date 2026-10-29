---
title: CORS
description: How Nette API sends Access-Control-Allow-Origin with setCorsHeader() or the setCorsAllowedOrigins() allowlist, and answers preflight OPTIONS requests.
---

# CORS

A browser app on another origin needs CORS headers. `ApiPresenter` adds them to every response, and `ApiDecider` can answer the preflight `OPTIONS` requests.

<Diagram name="cors-1" alt="The browser sends OPTIONS, gets the Access-Control headers, then sends the POST and gets 201 with Access-Control-Allow-Origin" />

::: warning Prefer the allowlist over 'auto'
`'auto'` trusts the `Referer` header and allows credentials for any site. Use [`setCorsAllowedOrigins()`](#allowed-origins) (added in 3.6.0) or one fixed origin. `'auto'` keeps working as it is in 3.x and is planned to be removed in 4.0 ([#186](https://github.com/tomaj/nette-api/issues/186), [4.0.0 milestone](https://github.com/tomaj/nette-api/milestone/9)).
:::

## Access-Control-Allow-Origin

Every response gets `Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS`, plus one of these, chosen with `ApiPresenter::setCorsHeader()`:

| Value | Headers sent |
| --- | --- |
| `'*'` (the default; with the [DI extension](/guide/di-extension#cors) the default is `'off'`) | `Access-Control-Allow-Origin: *` |
| `'https://app.example.com'` | `Access-Control-Allow-Origin: https://app.example.com` |
| `'auto'` | the scheme, host and port of the `Referer` header (any explicit port except `80` is kept, so `:443` stays), and `Access-Control-Allow-Credentials: true`; nothing when there is no Referer |
| `'off'` | no `Access-Control-Allow-Origin` |

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsHeader('https://app.example.com')
```

In PHP you can pass the `Presenters\CorsMode` enum instead of the predefined strings: `CorsMode::Any` (`'*'`), `CorsMode::Auto` (`'auto'`) and `CorsMode::Off` (`'off'`).

This is the one `apiPresenter` service from [Getting started](/guide/getting-started#_3-register-the-services): add these `setup:` lines to it, or put the block in another config file under the same name and Nette merges the two. Do not register a second `ApiPresenter` service; two of them fail with "Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found".

## Allowed origins

::: tip Since 3.6.0
This is the recommended setup. On 3.5.0 use one fixed origin instead.
:::

`setCorsAllowedOrigins(array $origins, bool $allowCredentials = false)` compares the request `Origin` header with a list. When it is set, it takes precedence over `setCorsHeader()`.

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsAllowedOrigins(['https://app.example.com', 'http://localhost:3000'], true)
```

| Request | Headers sent |
| --- | --- |
| `Origin` on the list | `Access-Control-Allow-Origin` with that origin, `Vary: Origin`, and `Access-Control-Allow-Credentials: true` when the second argument is `true` |
| other `Origin`, or none | only `Vary: Origin`, so the browser blocks the response |

The comparison ignores case, and default ports are dropped (`https://app.example.com:443` matches `https://app.example.com`). Each origin must be `scheme://host[:port]`: no path, no trailing slash, no `*` or `null`, and punycode for international domains. Anything else, or an empty list, throws `InvalidArgumentException`.

To move off `'auto'`, list the origins that really call the API (frontends and local development hosts) and replace `setCorsHeader('auto')` with `setCorsAllowedOrigins([...], true)`. Keep `true` only if you rely on cookies or `withCredentials`.

## Preflight requests

Before a `POST` with a JSON body or an `Authorization` header, the browser sends `OPTIONS` to the same URL. `enableGlobalPreflight()` answers it for every registered endpoint:

<<< @/../examples/cors/config/common.neon{yaml}

`CorsPreflightHandler` adds the headers you give it and answers `200` with `[]`. Without arguments it sends `Access-Control-Allow-Headers: Authorization` and `X-Requested-With`. `OPTIONS` to an unknown URL still answers `404`.

## FAQ

### Is 'auto' safe?

Not for APIs that use cookies: any page a logged-in user visits can call the API with their cookies and read the response. Use a fixed origin, or the [allowlist](#allowed-origins).

### How do I allow several origins?

With [`setCorsAllowedOrigins()`](#allowed-origins). On 3.5.0, which has no list, pick one origin, or use `'off'` and add the headers in your web server.

### My preflight answers 404. Why?

Call `enableGlobalPreflight()`, and check that an endpoint with the same version, package and action exists. The [CORS example](/examples/cors) tests both.

### Do I need CORS for server-to-server calls?

No, only browsers check it.

### Is the CORS header sent with errors too?

Yes, it is added before anything else, so `401`, `404` and `500` carry it as well. See [Architecture](/guide/architecture).
