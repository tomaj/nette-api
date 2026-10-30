---
title: CORS
description: How Nette API sends Access-Control-Allow-Origin with setCorsHeader(), the setCorsAllowedOrigins() allowlist or a setCorsOriginValidator() callback, and answers preflight OPTIONS requests.
---

# CORS

A browser app on another origin needs CORS headers. `ApiPresenter` adds them to every response, and `ApiDecider` can answer the preflight `OPTIONS` requests.

<Diagram name="cors-1" alt="The browser sends OPTIONS, gets the Access-Control headers, then sends the POST and gets 201 with Access-Control-Allow-Origin" />

::: danger 'auto' / 'reflect-unsafe' is unsafe, prefer the allowlist
`'auto'` (named `'reflect-unsafe'` in 4.0) sends the `Referer` origin back and allows credentials, so any website a logged-in user visits can call the API with their cookies and read the response. Use [`setCorsAllowedOrigins()`](#allowed-origins) (added in 3.6.0) or one fixed origin. `'auto'` keeps working as it is in 3.x. In 4.0 it is renamed to `'reflect-unsafe'` ([#186](https://github.com/tomaj/nette-api/issues/186)): `setCorsHeader('auto')` and `api: cors: mode: auto` throw an error that shows the allowlist to use instead and how to keep the old behaviour, and `ApiPresenter` sends no CORS headers until you configure them (default `'off'` instead of `'*'`).
:::

## Access-Control-Allow-Origin

Every response gets `Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS`, plus one of these, chosen with `ApiPresenter::setCorsHeader()`:

| Value | Headers sent |
| --- | --- |
| `'*'` (the default in 3.x; in 4.0 and with the [DI extension](/guide/di-extension#cors) the default is `'off'`) | `Access-Control-Allow-Origin: *` |
| `'https://app.example.com'` | `Access-Control-Allow-Origin: https://app.example.com` |
| `'auto'` | the scheme, host and port of the `Referer` header (any explicit port except `80` is kept, so `:443` stays), and `Access-Control-Allow-Credentials: true`; nothing when there is no Referer. **Unsafe.** Renamed to `'reflect-unsafe'` in 4.0 (which also always sends `Vary: Origin, Referer`), `'auto'` then throws |
| `'off'` | no `Access-Control-Allow-Origin` |

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsHeader('https://app.example.com')
```

In PHP you can pass the `Presenters\CorsMode` enum instead of the predefined strings: `CorsMode::Any` (`'*'`), `CorsMode::Auto` (`'auto'`; in 4.0 `CorsMode::ReflectUnsafe`, `'reflect-unsafe'`) and `CorsMode::Off` (`'off'`).

In 4.0 `setCorsHeader()` accepts only these modes or one origin in the form `scheme://host[:port]`. Anything else (`'OFF'`, `'null'`, `'https://app.example.com/'`, a list) throws `InvalidArgumentException` instead of being sent as a header no browser accepts.

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

The comparison ignores case. In the list, a default port is dropped, so the entry `https://app.example.com:443` matches the request origin `https://app.example.com` (browsers never send a default port). Each origin must be `scheme://host[:port]`: no path, no trailing slash, no `*` or `null`, and punycode for international domains. Anything else, or an empty list, throws `InvalidArgumentException`.

To move off `'auto'`, list the origins that really call the API (frontends and local development hosts) and replace `setCorsHeader('auto')` with `setCorsAllowedOrigins([...], true)`. Keep `true` only if you rely on cookies or `withCredentials`.

## Dynamic origins

::: tip Since 4.0.0
On 3.x add the headers in your web server or in an `Application::$onRequest` handler.
:::

When the origins are not known in advance (for example customer domains stored in a database), `setCorsOriginValidator(callable|CorsOriginValidatorInterface $validator, bool $allowCredentials = false)` decides on each request. It gets the request `Origin` header and returns `true` to allow it:

```php
$presenter->setCorsOriginValidator(
	fn (string $origin): bool => $customerDomains->exists($origin),
	allowCredentials: true,
);
```

In neon, pass a class implementing `Tomaj\NetteApi\Misc\CorsOriginValidatorInterface`:

```php
namespace App\Api;

use Tomaj\NetteApi\Misc\CorsOriginValidatorInterface;

final class CustomerOrigins implements CorsOriginValidatorInterface
{
	public function __construct(private CustomerRepository $customers)
	{
	}

	public function isAllowedOrigin(string $origin): bool
	{
		return $this->customers->hasDomain($origin);
	}
}
```

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsOriginValidator(@App\Api\CustomerOrigins, true)
	- App\Api\CustomerOrigins
```

With the [DI extension](/guide/di-extension#cors) write `api: cors: {originValidator: App\Api\CustomerOrigins, allowCredentials: true}`.

| Request | Headers sent |
| --- | --- |
| the validator returns `true` | `Access-Control-Allow-Origin` with exactly that origin, `Vary: Origin`, and `Access-Control-Allow-Credentials: true` when the second argument is `true` |
| the validator returns `false`, no `Origin`, or a value that is not an origin | only `Vary: Origin`, so the browser blocks the response |

- The validator gets only an origin in the form `scheme://host[:port]`, as browsers send it. `null`, a path, a list or anything with spaces never reaches it, so it cannot let an invalid value into the header. Compare the origin exactly (`===`, `in_array(..., true)`), never with `str_contains()` or a regular expression without anchors: `https://example.com.evil.com` contains `example.com`.
- Credentials are allowed, because the validator is an allowlist too. Return `true` only for origins you trust with the user's cookies.
- The validator runs once per request that has a valid `Origin` header (cross-origin requests, their preflights and also same-origin `POST`s), so cache slow lookups. An exception it throws is not caught by the error handler; return `false` when the lookup fails.
- When set, it takes precedence over `setCorsHeader()`. It cannot be combined with `setCorsAllowedOrigins()`: calling both throws `LogicException`, check the fixed origins in the validator instead.

Mistakes fail with a message that says what is wrong and how to fix it: a value that is not callable (for example a list of origins, which belongs to `setCorsAllowedOrigins()`), a callable with more than one required parameter, or with a declared return type other than `bool` throws `InvalidArgumentException` when you set it. A validator that returns something else than a bool at run time throws `UnexpectedValueException`, for example:

```
The CORS origin validator returned int for origin 'https://app.example.com', but it must return true (allow the origin) or false (reject it). Return a bool, for example return in_array($origin, $origins, true);
```

## Preflight requests

Before a `POST` with a JSON body or an `Authorization` header, the browser sends `OPTIONS` to the same URL. `enableGlobalPreflight()` answers it for every registered endpoint:

<<< @/../examples/cors/config/common.neon{yaml}

`CorsPreflightHandler` adds the headers you give it and answers `200` with `[]`. Without arguments it sends `Access-Control-Allow-Headers: Authorization` and `X-Requested-With`. `OPTIONS` to an unknown URL still answers `404`.

## FAQ

### Is 'auto' safe?

Not for APIs that use cookies: any page a logged-in user visits can call the API with their cookies and read the response. Use a fixed origin, or the [allowlist](#allowed-origins). That is why 4.0 renames it to `'reflect-unsafe'`, so the risk is visible where it is configured.

### Can I keep the 'auto' behaviour in 4.0?

Yes, as an explicit opt-in: `setCorsHeader('reflect-unsafe')` (or `CorsMode::ReflectUnsafe`, or `api: cors: {mode: reflect-unsafe}`) behaves like `'auto'` in 3.x, and also sends `Vary: Origin, Referer` so shared caches do not reuse the reflected origin for another site. The old name `'auto'` throws, with a message that shows both the allowlist and this option. It keeps the same hole: any website can make requests with the user's cookies and read the responses. Use it only when the API uses no cookies or other browser credentials, or while you migrate. Otherwise list your origins with the [allowlist](#allowed-origins). If the origins really are not known in advance (for example customer domains stored in a database), use a [validator](#dynamic-origins).

### How do I allow several origins?

With [`setCorsAllowedOrigins()`](#allowed-origins), or with a [validator](#dynamic-origins) when the list is not known in advance. On 3.5.0, which has no list, pick one origin, or use `'off'` and add the headers in your web server.

### My preflight answers 404. Why?

Call `enableGlobalPreflight()`, and check that an endpoint with the same version, package and action exists. The [CORS example](/examples/cors) tests both.

### Do I need CORS for server-to-server calls?

No, only browsers check it.

### Is the CORS header sent with errors too?

Yes, it is added before anything else, so `401`, `404` and `500` carry it as well. See [Architecture](/guide/architecture).
