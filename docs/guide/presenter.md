---
title: ApiPresenter pipeline
description: 'The exact order of steps in Nette API ApiPresenter::run(), from CORS and endpoint lookup to authorization, handler, output check and logging, with each exit code.'
---

# ApiPresenter pipeline

`Tomaj\NetteApi\Presenters\ApiPresenter::run()` is the whole request in one method. This page follows it step by step, from the source of 3.x.

<Diagram name="presenter-1" alt="run() sends CORS headers, finds the endpoint, checks the rate limit, reads params, checks authorization, reports param errors, calls the handler, validates the output and logs the request; exits with 404, 429, 401, 400 and 500" />

| Step | Code | Exit |
| --- | --- | --- |
| 1. CORS headers | `sendCorsHeaders()` | |
| 2. Endpoint | `$this->apiDecider->getApi(...)` | `404` through `DefaultHandler` |
| 3. Rate limit | `checkRateLimit($rateLimit)` | `429`, not logged |
| 4. Read params | `new ParamsProcessor($handler->params())` | |
| 5. Authorization | `checkAuth($authorization, $params)` | `401` or `500`, not logged |
| 6. Param errors | `errorHandler->handleInputParams()` | `400`, not logged |
| 7. Handler | `$handler->handle($params)` | exception: `500` |
| 8. Output check | `$output->validate($response)` | no match: `500` |
| 9. Logger | `logRequest()` | |

## 1. CORS

Headers go out first, so even a `404` or `401` carries them and the browser can read the error. `setCorsAllowedOrigins()` takes precedence over `setCorsHeader()`. See [CORS](/guide/cors).

## 2. Endpoint lookup

The method and the `version`, `package` and `apiAction` route parameters go to `ApiDecider`. An unknown URL gets `DefaultHandler`, which goes through the remaining steps and answers `404`, and is logged. See [ApiDecider and endpoints](/guide/api-decider).

## 3. Rate limit

```php
$rateLimitResponse = $rateLimit->check();
// adds X-RateLimit-Limit and X-RateLimit-Remaining
if ($remaining === 0) {
    // 429, Retry-After, your error response or "Too many requests. Retry after N seconds."
}
```

Before authorization, so failed logins count. See [Rate limiting](/guide/rate-limit).

## 4. Params are read

```php
$paramsProcessor = new ParamsProcessor($handler->params());
$params = $paramsProcessor->isError() ? [] : $paramsProcessor->getValues();
```

Every param is read and validated. With an error, `$params` is empty and the error waits for step 6.

## 5. Authorization

`authorized()` returning `false` goes to `handleAuthorization()`: `401` with `getErrorMessage()`. An exception goes to `handleAuthorizationException()`: `500` with the exception message, so do not put secrets in it. See [Authorization](/guide/authorization).

## 6. Param errors

Only now, after authorization, a wrong input answers `400`:

```json
{"status": "error", "message": "wrong input", "detail": {"page": ["Field is required"]}}
```

`detail` is shown unless the configurator hides it, as `EnvConfigurator` does in production. See [Input params](/guide/params).

## 7. The handler

`handle($params)` runs inside `try`. Any `Throwable` goes to `ErrorHandlerInterface::handle()`, which logs it to Tracy and answers `500`. See [Error handling](/guide/errors).

## 8. Output check

When `ConfiguratorInterface::validateSchema()` is true, the response is compared with each of `outputs()`; **the first match is enough**. No match goes to `handleSchema()` and `500`. An empty `outputs()` is not checked. Every code your handler returns needs an output, which the [pagination example](/examples/pagination) shows for `400` and `404`. See [Outputs](/guide/outputs).

## 9. Logging

If the container has an `ApiLoggerInterface`, the request is logged with its final code and time. Headers listed by `setMaskedLogHeaders()` are logged as `***`. Steps 3, 5 and 6 returned already, so `429`, `401` and `400` are not logged in 3.x. See [Logging](/guide/logging).

## Configuring the presenter

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsAllowedOrigins(['https://app.example.com'])
			- setMaskedLogHeaders()
```

This is the one `apiPresenter` service from [Getting started](/guide/getting-started#_3-register-the-services): add these `setup:` lines to it, or put the block in another config file under the same name and Nette merges the two. Do not register a second `ApiPresenter` service; two of them fail with "Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found".

The class is `final`. Change its behaviour through these setters and through the services it takes from the container: `ErrorHandlerInterface`, `ConfiguratorInterface`, `ApiLoggerInterface` and `IpDetectorInterface`.

## FAQ

### Why does wrong input with a wrong token answer 401?

Authorization (step 5) runs before param errors (step 6).

### Why is my 400 turned into 500?

The handler returned `400` but no output with code `400` matches. Add one, or turn validation off in production.

### Does the rate limit see the authorized user?

No, it runs before authorization. Identify the client by its key or IP, as the [rate limit example](/examples/rate-limit) does.

### Is the response time logged with the rate limit or auth checks?

Only for logged requests: the time from the start of `run()` to after the output check.

### Can I add a step, for example a request ID?

Not inside `run()`. Do it in the authorization, the handler, or a Nette `onRequest` / `onResponse` event.
