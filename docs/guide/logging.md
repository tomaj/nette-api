---
title: Logging
description: Log Nette API requests with ApiLoggerInterface - what log() receives, which responses are logged in 3.x, and how secret headers and query parameters are masked.
---

# Logging

When the DI container has a service implementing `Tomaj\NetteApi\Logger\ApiLoggerInterface`, `ApiPresenter` calls it after the handler ran.

<Diagram name="logging-1" alt="The handler response, or the error handler response after an exception, is passed to ApiLoggerInterface::log" />

::: tip Logs are masked by default since 4.0.0
The presenter replaces secret header values and secret query parameter values with `***` before they reach `log()`. See [Masking](#masking). In 3.x the logger got the headers exactly as they arrived, unless you called `setMaskedLogHeaders()`.
:::

## The interface

```php
public function log(
    int $responseCode,
    string $requestMethod,
    string $requestHeader,
    string $requestUri,
    string $requestIp,
    string $requestAgent,
    int $responseTime
): bool;
```

| Argument | Value |
| --- | --- |
| `$responseCode` | the status code sent |
| `$requestHeader` | all request headers, one `Name: value` per line, [masked](#masking) |
| `$requestUri` | `REQUEST_URI`, with the query string, [masked](#masking) |
| `$requestIp` | from the `IpDetectorInterface` service, which must exist |
| `$responseTime` | milliseconds |

## Example

The [logging example](/examples/logging-errors) writes JSON lines:

<<< @/../examples/logging-errors/app/FileApiLogger.php

```yaml
services:
	- Tomaj\NetteApi\Misc\IpDetector
	- App\FileApiLogger(%appDir%/../log/api.log)
```

## Masking

::: tip Since 4.0.0
Masking is on by default. 3.6.0 had only `setMaskedLogHeaders()`, and it was off until you called it. See [UPGRADE-4.0.md](https://github.com/tomaj/nette-api/blob/master/UPGRADE-4.0.md#logs-are-masked-by-default).
:::

Before `log()` runs, the presenter replaces the value of every secret header and every secret query parameter. The name stays, so you still see what the client sent. This request:

```text
GET /api/v1/users?api_key=sk_live_4f9a8b7c6d5e&page=2
Authorization: Bearer eyJhbGciOiJIUzI1NiJ9
X-Api-Key: abc123
Cookie: lang=en; PHPSESSID=9f8e7d6c5b4a
X-Request-Id: abc-123
```

reaches `log()` as:

```text
$requestUri    = /api/v1/users?api_key=***&page=2
$requestHeader = Authorization: ***
                 X-Api-Key: ***
                 Cookie: ***
                 X-Request-Id: abc-123
```

Masking needs no configuration. You change it in one of two places:

- the `setup:` of the `apiPresenter` service, with the [presenter methods](#presenter-methods), or
- the `logging:` section of the [DI extension](#neon-reference).

### What is masked by default

| What | Default | Constant |
| --- | --- | --- |
| header names | `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key` | `ApiPresenter::DEFAULT_MASKED_LOG_HEADERS` |
| header patterns | `*token*`, `*secret*`, `*key*`, `x-auth-*` | `ApiPresenter::DEFAULT_MASKED_LOG_HEADER_PATTERNS` |
| query parameters | `api_key`, `apikey`, `key`, `token`, `access_token`, `refresh_token`, `id_token`, `auth`, `password`, `secret`, `signature`, `sig`, `code`, `client_secret` | `ApiPresenter::DEFAULT_MASKED_LOG_QUERY_PARAMS` |
| authorization names | the header of every `HeaderApiKeyAuthentication`, the parameter of every `QueryApiKeyAuthentication`, the cookie of every `CookieApiKeyAuthentication` | read from the registered endpoints on each request |
| mode | `***` | `LogMaskMode::Full` |

### How entries match

- **Header names** compare case-insensitively: `authorization`, `Authorization` and `AUTHORIZATION` are one entry.
- **A header pattern** is a header name in which `*` stands for any characters, also none. It must match the whole name, case-insensitively. `*token*` matches `X-Auth-Token`, `X-Refresh-Token` and `Token`. `x-auth-*` matches `X-Auth-User`, but not `X-Auth` (the `-` is required). No other wildcard exists: `?` and `[...]` are not allowed in a header name, so they are rejected.
- **Query parameters** compare case-insensitively after URL-decoding the name. Each name also masks its array forms: `token` masks `token=`, `TOKEN=`, `token[]=` and `token[a]=`. A parameter without `=` (`?token`) has no value and stays. An empty value (`?token=`) is masked like any other value.
- **Authorization names** are added to the lists when the request is logged. Header and query parameter names compare case-insensitively, cookie names exactly, as PHP reads cookies. You cannot remove them one by one, because they are not in the lists and `getMasked*()` does not return them. They go only when you turn that kind of masking off. To log one of them, turn that kind of masking off and mask the rest in your own logger, or log a hash of it with `masking: hash`.
- **`Cookie`** is in the default names, so the whole header becomes `***`. If you remove `Cookie`, the header is logged, and only the cookies read by a `CookieApiKeyAuthentication` are masked inside it: `Cookie: lang=en; api_session=***`.
- **The last casing wins**: `addMaskedLogHeader('COOKIE')` keeps one entry, and `getMaskedLogHeaders()` returns `COOKIE`.
- **Order of the lists:** `getMasked*()` returns the entries in the order they were added, with the defaults first.

### Quick recipes

Every recipe shows the presenter `setup:` first, then the same with the DI extension. Use only one of them for each list; see [Presenter setup and neon together](#presenter-setup-and-neon-together).

**Mask one more header, query parameter or header pattern:**

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- addMaskedLogHeader(X-Session)
			- addMaskedLogHeaderPattern(x-signature-*)
			- addMaskedLogQueryParam(session_id)
```

```neon
api:
	logging:
		maskHeaders: {add: [X-Session], patterns: {add: [x-signature-*]}}
		maskQueryParams: {add: [session_id]}
```

**Log one default in full** (here `Set-Cookie`, the `*key*` pattern and `code`):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- removeMaskedLogHeader(Set-Cookie)
			- removeMaskedLogHeaderPattern('*key*')
			- removeMaskedLogQueryParam(code)
```

```neon
api:
	logging:
		maskHeaders: {remove: [Set-Cookie], patterns: {remove: ['*key*']}}
		maskQueryParams: {remove: [code]}
```

**Replace a whole list** (the header patterns and the authorization names stay):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setMaskedLogHeaders([Authorization, X-Session])
			- setMaskedLogHeaderPatterns(['x-secret-*'])
			- setMaskedLogQueryParams([api_key, token])
```

```neon
api:
	logging:
		maskHeaders: {names: [Authorization, X-Session], patterns: ['x-secret-*']}
		maskQueryParams: [api_key, token]
```

**Turn masking off**, to log everything as in 3.x:

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setMaskedLogHeaders([])
			- setMaskedLogQueryParams([])
```

```neon
api:
	logging:
		maskHeaders: false
		maskQueryParams: false
```

`setMaskedLogHeaders([])` turns off the names, the patterns and the authorization headers and cookies. `setMaskedLogQueryParams([])` turns off the parameters and the authorization parameters. You can turn off only one of them.

**Change the mode**, see [Masking modes](#masking-modes):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setLogMasking(Tomaj\NetteApi\Logger\LogMaskMode::Partial)
```

```neon
api:
	logging:
		masking: partial
```

**Use your own masker**, see [Custom masker](#custom-masker):

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setLogMasking(App\Api\LengthMasker())
```

```neon
api:
	logging:
		masking: App\Api\LengthMasker
```

The `apiPresenter` above is the one service from [Getting started](/guide/getting-started#_3-register-the-services): add the `setup:` lines to it, or put the block in another config file under the same name and Nette merges the two. Do not register a second `ApiPresenter` service; two of them fail with "Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found". In NEON quote a pattern that starts with `*`: `'*key*'`.

### Presenter methods

| Method | Effect |
| --- | --- |
| `setMaskedLogHeaders(array $headers = DEFAULT_MASKED_LOG_HEADERS)` | replaces the header names. Without an argument it restores the defaults. The patterns stay. |
| `setMaskedLogHeaders([])` | turns header masking off: clears the names and the patterns, and skips the authorization headers and cookies |
| `addMaskedLogHeader(string $header)` | adds one name. After `setMaskedLogHeaders([])` it turns header masking on again, with the authorization names, but not the cleared patterns. |
| `removeMaskedLogHeader(string $header)` | removes one name. It fails when the name is not in the list. Removing the last name keeps the patterns and the authorization names. |
| `setMaskedLogHeaderPatterns(array $patterns = DEFAULT_MASKED_LOG_HEADER_PATTERNS)` | replaces the patterns. `[]` clears only the patterns; the names stay. A non-empty list turns header masking on again. |
| `addMaskedLogHeaderPattern(string $pattern)` | adds one pattern, and turns header masking on again |
| `removeMaskedLogHeaderPattern(string $pattern)` | removes one pattern. It fails when the pattern is not in the list. |
| `setMaskedLogQueryParams(array $params = DEFAULT_MASKED_LOG_QUERY_PARAMS)` | replaces the parameters. `[]` turns query masking off, with the authorization parameters. |
| `addMaskedLogQueryParam(string $param)` | adds one parameter, and turns query masking on again |
| `removeMaskedLogQueryParam(string $param)` | removes one parameter. It fails when the parameter is not in the list. |
| `getMaskedLogHeaders()`, `getMaskedLogHeaderPatterns()`, `getMaskedLogQueryParams()` | `list<string>`: the current lists, without the authorization names |
| `setLogMasking(LogMaskMode\|LogValueMaskerInterface $masking)` | how a value is masked. A `LogMaskMode` becomes `new LogValueMasker($mode)`. For a hash key, pass `new LogValueMasker(LogMaskMode::Hash, $key)`. |
| `getLogMasking()` | the current `LogValueMaskerInterface`, by default `LogValueMasker` with `LogMaskMode::Full` |

The calls run in the order of `setup:`. Each one changes what the earlier ones left, so `setMaskedLogHeaders([X-A])` followed by `addMaskedLogHeader(X-B)` masks both, and the other order masks only `X-A`.

Every entry is checked when the call runs. A mistake fails with what is wrong and how to fix it:

```text
removeMaskedLogHeader('Set-Cokie'): 'Set-Cokie' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. Did you mean 'Set-Cookie'? Entries compare case-insensitively; fix the spelling or leave the entry out.
removeMaskedLogHeader('X-Auth-Token'): 'X-Auth-Token' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. It is masked by the pattern '*token*'; remove the pattern, or replace the patterns with a narrower list.
addMaskedLogHeader('X-*'): it contains *, which is only for header patterns. Add it as a pattern, or write the full header name.
setMaskedLogHeaders(): 'X Secret' in the list: it is not an HTTP header name, a header name has no spaces, colons or other separators. Write only the name, for example X-Session.
addMaskedLogHeaderPattern('token'): it has no *, so it matches only one header. Add it as a header name, or use * for any characters, for example *token*.
addMaskedLogHeaderPattern('*'): it matches every header, so nothing useful would be logged. Name a part of the header, for example *token*, or leave the headers out in your logger.
addMaskedLogQueryParam('token[]'): it is not a query parameter name. Write only the name without =, &, ?, # or brackets, for example api_key (it also masks api_key[] and api_key[x]).
removeMaskedLogQueryParam('cod'): 'cod' is not in the masked query parameters (api_key, apikey, key, token, access_token, refresh_token, id_token, auth, password, secret, signature, sig, code, client_secret), so there is nothing to remove. Did you mean 'code'? Entries compare case-insensitively; fix the spelling or leave the entry out.
new LogValueMasker(LogMaskMode::Partial, hashKey): the hash key is used only by LogMaskMode::Hash. Use new LogValueMasker(LogMaskMode::Hash, $key), or leave the key out.
```

A wrong call in `setup:` fails on the first request, when the presenter is created. With the DI extension the same mistakes fail when the container is compiled.

### Neon reference

With the [DI extension](/guide/di-extension#logging) masking is set in `api: logging:`. Leave the section out and the presenter defaults apply.

```neon
api:
	logging:
		maskHeaders: true          # true | false | list of names | map, see below
		maskQueryParams: true      # true | false | list of names | map, see below
		masking: full              # full | partial | hash | omit | Class | Class(args) | @service
		hashKey: null              # string, only with masking: hash
```

| Key | Type | Default | Effect |
| --- | --- | --- | --- |
| `maskHeaders` | `true` | yes | the default names, patterns and authorization names |
| | `false` | | header masking off: no names, no patterns, no authorization headers or cookies (`setMaskedLogHeaders([])`) |
| | list, `[Authorization, X-Session]` | | replaces the default names; the default patterns and the authorization names stay (`setMaskedLogHeaders([...])`) |
| | map `{names, add, remove, patterns}` | | changes the defaults one by one, see below |
| `maskHeaders.names` | list | the default names | replaces the default names before `add` and `remove` |
| `maskHeaders.add` | list | `[]` | adds names |
| `maskHeaders.remove` | list | `[]` | removes names that are in the defaults or in `names` |
| `maskHeaders.patterns` | `true` | yes | the default patterns |
| | `false` | | no patterns; the names stay |
| | list, `['*token*', 'x-auth-*']` | | replaces the default patterns |
| | map `{add, remove}` | | adds or removes patterns |
| `maskQueryParams` | `true` | yes | the default parameters and the authorization parameters |
| | `false` | | query masking off, also for the authorization parameters |
| | list, `[api_key, token]` | | replaces the default parameters; the authorization parameters stay |
| | map `{names, add, remove}` | | the same as for `maskHeaders`, without `patterns` |
| `masking` | `full`, `partial`, `hash`, `omit` | `full` | the [mode](#masking-modes) |
| | `App\Api\MyMasker`, `App\Api\MyMasker(args)`, `@myMasker` | | your [masker](#custom-masker); the class must implement `LogValueMaskerInterface` |
| `hashKey` | string, `%env.API_LOG_HASH_KEY%` | `null` | the HMAC key for `masking: hash`. Without it, plain SHA-256 is used. |

A map applies `names` first, then `add`, then `remove`, and turns them into the same presenter calls. Every map key is optional, but the map must change something:

```neon
api:
	logging:
		maskHeaders:
			names: [Authorization, Cookie, X-Session]   # instead of the 5 defaults
			add: [X-Device-Id]                          # then add
			remove: [Cookie]                            # then remove, only from names or the defaults
			patterns:
				add: [x-signature-*]
				remove: ['*key*']
		maskQueryParams:
			add: [session_id]
			remove: [code]
		masking: hash
		hashKey: %env.API_LOG_HASH_KEY%
```

This masks `Authorization`, `X-Session` and `X-Device-Id`, the headers that match `*token*`, `*secret*`, `x-auth-*` or `x-signature-*`, and the authorization names. `X-Api-Key` is logged, because it is not in `names` and `*key*` was removed. `Cookie` is logged in full, except the cookies of a `CookieApiKeyAuthentication`. The query parameters are the 13 defaults without `code`, plus `session_id`. Values become `hash:` plus 16 hex characters of an HMAC.

`true` and `masking: full` add no call, so the presenter keeps its defaults. The other values add `setMaskedLog*` / `add*` / `remove*` / `setLogMasking` calls to the presenter when the container is compiled.

### Presenter setup and neon together

The extension adds its calls after the `setup:` of the `apiPresenter` service, so they would replace what the setup changed. When a `logging:` key that is not left at its default meets a setup call for the same thing, compiling fails:

```text
api.logging.maskHeaders and the setup of the Tomaj\NetteApi\Presenters\ApiPresenter service call addMaskedLogHeader() both change the masked headers. The extension runs after the setup and would replace it. Keep one place: move the setup call into api.logging.maskHeaders (for example addMaskedLogHeader(X-Session) is maskHeaders: {add: [X-Session]}), or leave api.logging.maskHeaders out.
```

| `logging:` key | conflicting presenter `setup:` calls |
| --- | --- |
| `maskHeaders` | `setMaskedLogHeaders`, `addMaskedLogHeader`, `removeMaskedLogHeader`, `setMaskedLogHeaderPatterns`, `addMaskedLogHeaderPattern`, `removeMaskedLogHeaderPattern` |
| `maskQueryParams` | `setMaskedLogQueryParams`, `addMaskedLogQueryParam`, `removeMaskedLogQueryParam` |
| `masking`, `hashKey` | `setLogMasking` |

The keys are separate. You can, for example, set the headers in `setup:` and `masking: hash` in neon. A key left at its default (`maskHeaders: true`, `masking: full`) never conflicts.

Neon mistakes fail when the container is compiled, and name the key:

```text
api.logging.maskHeaders.remove[0]: 'Set-Cokie' is not in the masked headers (Authorization, Proxy-Authorization, Cookie, Set-Cookie, X-Api-Key), so there is nothing to remove. Did you mean 'Set-Cookie'? Entries compare case-insensitively; fix the spelling or leave the entry out.
api.logging.maskHeaders.remove[0] 'X-A' is also in api.logging.maskHeaders.add, so it would be added and removed again. Keep it in one of them.
api.logging.maskHeaders is an empty map, so it changes nothing. Use true for the defaults, false to turn the masking off, or names, add or remove, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}.
api.logging.maskHeaders is an empty list. To turn the masking off write false; to change the defaults use add or remove, for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}.
api.logging.maskHeaders.patterns[0] 'token': it has no *, so it matches only one header. Add it as a header name, or use * for any characters, for example *token*.
api.logging.maskQueryParams.add[0] 'x[]': it is not a query parameter name. Write only the name without =, &, ?, # or brackets, for example api_key (it also masks api_key[] and api_key[x]).
api.logging.masking 'parital' is not a masking mode. Did you mean 'partial'? Use 'full', 'partial', 'hash', 'omit', or a class implementing Tomaj\NetteApi\Logger\LogValueMaskerInterface, @serviceName or a statement like App\Api\MyMasker(%tempDir%) for your own.
api.logging.hashKey is set, but api.logging.masking is 'full' and only hash uses the key. Use masking: hash with the key, or remove hashKey.
api.logging.hashKey is empty, so it would not protect short secrets. Use a long random secret, for example hashKey: %env.API_LOG_HASH_KEY%, or remove hashKey for a plain SHA-256.
Unexpected item 'api › logging › maskHeaders › ad', did you mean 'add'? Allowed keys in api.logging.maskHeaders: names, add, remove, patterns.
```

The whole list is in the [configuration errors](/guide/di-extension#configuration-errors) table.

### Masking modes

The mode decides what a masked value becomes. It is the same for headers, query parameters and cookies. The request from the top of this page is logged as:

| Mode | `$requestUri` | `$requestHeader` |
| --- | --- | --- |
| `full` (default) | `/api/v1/users?api_key=***&page=2` | `Authorization: ***`<br>`X-Api-Key: ***`<br>`Cookie: ***`<br>`X-Request-Id: abc-123` |
| `partial` | `/api/v1/users?api_key=sk_l***&page=2` | `Authorization: Bearer eyJh***`<br>`X-Api-Key: ***`<br>`Cookie: lang***`<br>`X-Request-Id: abc-123` |
| `hash` | `/api/v1/users?api_key=hash:2592602aa93aef79&page=2` | `Authorization: hash:5d78b6eb771afd1e`<br>`X-Api-Key: hash:6ca13d52ca70c883`<br>`Cookie: hash:83e1eafde364581e`<br>`X-Request-Id: abc-123` |
| `hash` with `hashKey: my-long-random-key` | `/api/v1/users?api_key=hash:97c5041103206d22&page=2` | `Authorization: hash:7fc0ed98d7c6352e`<br>`X-Api-Key: hash:e4984104dab10337`<br>`Cookie: hash:6f6bf54c6f1caeb4`<br>`X-Request-Id: abc-123` |
| `omit` | `/api/v1/users?page=2` | `X-Request-Id: abc-123` |

| Mode | Rule | Use |
| --- | --- | --- |
| `LogMaskMode::Full` | the value becomes `***` | nothing of the secret in the log |
| `LogMaskMode::Partial` | the first 4 characters stay and the rest becomes `***`. A value shorter than 12 characters becomes only `***`. A leading authentication scheme (`Basic`, `Bearer`, `Digest`, `DPoP`, `HOBA`, `Mutual`, `Negotiate`, `NTLM`, `Token`, `ApiKey`, `Hawk`, `AWS4-HMAC-SHA256`, `SCRAM-SHA-1`, `SCRAM-SHA-256`, `vapid`, any case) and its space are kept, and the rule applies to the rest: `Bearer short` becomes `Bearer ***`. | tell keys apart, see which client sent which key |
| `LogMaskMode::Hash` | `hash:` and the first 16 hex characters of SHA-256 of the whole value, or of HMAC-SHA-256 with `hashKey`. The scheme is hashed too. | find all requests with the same key without logging it |
| `LogMaskMode::Omit` | the header line is left out. The `name=value` pair is left out of the query, and without other parameters the `?` goes too (`/x?token=a` is logged as `/x`). A cookie of a `CookieApiKeyAuthentication` is left out of the `Cookie` header. | nothing about the secret, not even that it was sent |

Partial counts bytes, so keep it for ASCII secrets. For `Basic` the 4 kept characters are base64 of the start of the user name. For the whole `Cookie` header they are the start of the first cookie, `lang***`.

::: warning Hash is not encryption
Without `hashKey` the value is the first 16 hex characters of a plain SHA-256. Anyone who has the log can hash every candidate and compare. A 6-digit PIN, a word, or a short or known key is found in seconds. Set `hashKey` to a long random secret kept out of the log and out of the repository, for example `%env.API_LOG_HASH_KEY%`. The value is then an HMAC-SHA-256 that cannot be checked without the key. Without the DI extension pass `new LogValueMasker(LogMaskMode::Hash, $key)`. If you change the key, the same secret gives a different hash, so older log lines no longer match. The key works only with `hash`; with another mode it fails, and an empty key fails too.
:::

### Custom masker

For anything else implement `Tomaj\NetteApi\Logger\LogValueMaskerInterface`:

```php
namespace App\Api;

use Tomaj\NetteApi\Logger\LogValueMaskerInterface;

final class LengthMasker implements LogValueMaskerInterface
{
    public function mask(string $value): ?string
    {
        return '*** (' . strlen($value) . ' chars)';
    }
}
```

- `mask()` gets the original value: the whole header value, a URL-decoded query value, or the value of one cookie.
- It returns the text to log, or `null` to leave the header, the parameter or the cookie out, as `omit` does.
- A returned query value is URL-encoded again (only `*` and `:` stay as they are), so `*** (3 chars)` is logged as `?api_key=***%20%283%20chars%29`. A header gets the text as it is: `Authorization: *** (27 chars)`.
- The masker is one object for all requests. Keep secrets such as keys in its constructor.

```neon
api:
	logging:
		masking: App\Api\LengthMasker            # or App\Api\LengthMasker(%tempDir%), or @myMasker
```

Without the extension, `setLogMasking(App\Api\LengthMasker())` in `setup:`.

### What is not masked

- The body: `log()` never gets the request or the response body. If you log bodies elsewhere, mask them there.
- The `$requestAgent` and `$requestIp` arguments.
- The [console](/guide/console) and Tracy. The console shows the request you type, and Tracy's bar and bluescreen show the raw headers and `$_GET`. Keep Tracy in production mode, where the bluescreen is not shown, and mask its logs yourself if you need to.
- Path segments: `/api/v1/users/secret-id` stays as it is. Do not put secrets in the path.
- Response headers: only the request headers are logged.
- Your own `log()` calls elsewhere, for example to Tracy.

### Security notes

- The defaults cover common names only. Check what your clients send, and add your own names, for example `X-Session` or `session_id`.
- `removeMaskedLog*()`, `false` and a narrower list log real secrets. Prefer removing one entry to turning masking off.
- The authorization names are masked even if you replace the lists, so a key read by the API stays out of the log. Only `false`, `setMaskedLogHeaders([])` or `setMaskedLogQueryParams([])` turns them off.
- Logs are often kept longer and read by more people than the database. A masked log is still personal data when it has IPs and user agents.

## What is logged

| Response | Logged in 3.x |
| --- | --- |
| handler result, any code | yes |
| `500` from an exception or a failed output check | yes |
| `404 Unknown api endpoint` | yes |
| `400` wrong input, `401`, `429`, `500` from an exception in `authorized()` | no, the presenter returns before the logger |

## FAQ

### Why are my 401 responses missing from the log?

They return early in 3.x, see the table. Log them in your own [error handler](/guide/errors) if you need them.

### Do I need an IP detector for logging?

Yes, the presenter takes `IpDetectorInterface` from the container. Prefer one that reads only `REMOTE_ADDR`; see [Authorization](/guide/authorization#tokens-and-ip-restrictions).

### Where do exceptions go?

To Tracy, through `Debugger::log()` in `DefaultErrorHandler`. See [Error handling](/guide/errors).

### Can I log the response body?

Not through this interface, it gets only the code. Wrap the handler or the error handler if you need more.

### Does the return value of log() matter?

No, the presenter ignores it.

### Do I keep the masking in my own logger?

Not for headers and query parameters, the presenter masks them. Keep it for anything else you log, such as path segments or bodies.

### Why is my X-Request-Key header masked?

The `*key*` pattern matches it. Remove the pattern with `removeMaskedLogHeaderPattern('*key*')` or `maskHeaders: {patterns: {remove: ['*key*']}}`. `X-Api-Key` stays masked, because it is also in the names.

### How do I log the header of my HeaderApiKeyAuthentication?

You cannot remove an authorization name on its own; only `maskHeaders: false` (or `setMaskedLogHeaders([])`) turns it off with all other header masking. Log a hash instead, `masking: hash` with a `hashKey`, to tell the keys apart.

### Why does compiling fail with "both change the masked headers"?

The presenter `setup:` and the `logging:` section both change the same list, and the extension would replace the setup. Keep it in one place, see [Presenter setup and neon together](#presenter-setup-and-neon-together).

### Is the mode the same for headers and query parameters?

Yes, one masker handles every masked value. A [custom masker](#custom-masker) gets only the value, not the header or parameter name, so it cannot treat them differently either.
