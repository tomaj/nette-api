---
title: Logging
description: Log Nette API requests with ApiLoggerInterface - what log() receives, which responses are logged in 3.x, and how to mask secret headers.
---

# Logging

When the DI container has a service implementing `Tomaj\NetteApi\Logger\ApiLoggerInterface`, `ApiPresenter` calls it after the handler ran.

<Diagram name="logging-1" alt="The handler response, or the error handler response after an exception, is passed to ApiLoggerInterface::log" />

::: warning Mask secret headers
By default the logger gets the request headers exactly as they arrived, including `Authorization`, cookies and API keys. Since 3.6.0, call [`setMaskedLogHeaders()`](#masking-headers) on the presenter. In 3.5.0, mask them in your logger.
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
| `$requestHeader` | all request headers, one `Name: value` per line |
| `$requestUri` | `REQUEST_URI`, with the query string |
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

## Masking headers

::: tip Since 3.6.0
`ApiPresenter::setMaskedLogHeaders()` replaces the values of chosen request headers with `***` before they reach `log()`. It is off until you call it, so 3.x logging does not change by itself.
:::

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setMaskedLogHeaders()
```

This is the one `apiPresenter` service from [Getting started](/guide/getting-started#_3-register-the-services): add these `setup:` lines to it, or put the block in another config file under the same name and Nette merges the two. Do not register a second `ApiPresenter` service; two of them fail with "Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found".

| Call | Masked headers |
| --- | --- |
| `setMaskedLogHeaders()` | `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key` (`ApiPresenter::DEFAULT_MASKED_LOG_HEADERS`) |
| `setMaskedLogHeaders(['X-Session', 'Authorization'])` | only the listed ones, the list replaces the default |
| `setMaskedLogHeaders([])` | none |

Names compare case-insensitively, so `x-api-key` matches `X-Api-Key`. The header stays in the log with its name, only the value becomes `***`:

```text
Authorization: ***
X-Trace: abc-123
```

Only headers are masked. A token in the query string, such as `?api_key=` for `QueryApiKeyAuthentication`, stays in `$requestUri`; strip it in your logger.

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

Not for headers once you call `setMaskedLogHeaders()`. Keep it for query string tokens, which the presenter does not touch.
