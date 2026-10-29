---
title: Problem Details (RFC 9457)
description: 'Send Nette API errors as RFC 9457 problem details (application/problem+json) with ProblemDetailsErrorHandler, negotiate them by Accept and describe them in OpenAPI.'
---

# Problem Details (RFC 9457)

::: tip Recommended for new projects, since 4.0.0
Problem details are the recommended error format: RFC 9457 is a standard, so every error has the same machine-readable shape that clients and tools already understand. They are opt-in, not the default, because existing clients read `message` from the 3.x format in [Error handling](/guide/errors), which keeps working without any change.
:::

[RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) is the standard JSON body for HTTP API errors, sent as `application/problem+json`:

```json
{"type":"https://api.example.com/problems/validation-error","status":400,"title":"Bad Request","detail":"Request parameters are not valid.","errors":[{"parameter":"product","detail":"Field is required"}]}
```

| Member | Meaning |
| --- | --- |
| `type` | URI that identifies the kind of problem, `about:blank` when it is only the HTTP status |
| `status` | the HTTP status code, the same as the response |
| `title` | short summary of the kind, the same for every occurrence |
| `detail` | this occurrence, for a human |
| `instance` | URI of this occurrence, optional |
| anything else | extension members, for example `errors`, `limit`, `retryAfter` |

## Switching it on

Register `ProblemDetailsErrorHandler` instead of `DefaultErrorHandler`, in the same place in `config/api.neon` from [Getting started](/guide/getting-started#_3-register-the-services):

```yaml
services:
	# replaces - Tomaj\NetteApi\Error\DefaultErrorHandler
	- Tomaj\NetteApi\Error\ProblemDetailsErrorHandler(typeBaseUri: 'https://api.example.com/problems/')
```

Keep only one error handler service: with two, Nette DI cannot autowire `ErrorHandlerInterface` and the container fails with "Multiple services of type". It uses the output configurator you already have, the same as `DefaultErrorHandler`. A missing trailing slash in `typeBaseUri` is added. Without `typeBaseUri`, or with an empty one, every `type` is `about:blank`.

## What each error looks like

| Called for | Status | `type` slug | `detail` | Extensions |
| --- | --- | --- | --- | --- |
| invalid params, `handleInputParams()` | `400` | `validation-error` | `Request parameters are not valid.` | `errors`, always |
| `authorized()` is false, `handleAuthorization()` | `401` | `unauthorized` | `getErrorMessage()` | |
| unknown endpoint, `handleNotFound()` | `404` | `not-found` | `Unknown api endpoint` | |
| rate limit reached, `handleRateLimit()` | `429` | `rate-limited` | `Too many requests.`, plus `Retry after N seconds.` when known | `limit`, `retryAfter` when known |
| exception in the handler, `handle()` | `500` | `internal-error` | the message, only with error details on | |
| exception in `authorized()`, `handleAuthorizationException()` | `500` | `internal-error` | the message, only with error details on | |
| output does not match, `handleSchema()` | `500` | `invalid-output` | none | `outputErrors`, only with error details on |

The `type` is `typeBaseUri` followed by the slug. Every exception is logged to Tracy.

Validation errors are a list of `{"parameter": ..., "detail": ...}` and are sent in production too, because the client needs them to fix the request. Exception messages and output schema errors are sent only when `showErrorDetail()` is true. Output schema errors go to `outputErrors`, one list per declared output, so `errors` always has the shape above.

## Your own problems from a handler

A handler returns a business error, such as an order of a sold-out product, as `ProblemDetailsResponse`:

```php
use Tomaj\NetteApi\Error\ProblemDetails;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;

return new ProblemDetailsResponse(new ProblemDetails(
    status: 409,
    title: 'Out of stock',
    detail: 'Product sold-out has 0 pieces left, 2 requested.',
    type: 'https://api.example.com/problems/out-of-stock',
    instance: '/api/v1/orders',
    extensions: ['product' => 'sold-out', 'available' => 0],
));
```

`ProblemDetails` accepts only `4xx` and `5xx` codes and refuses extensions named `type`, `status`, `title`, `detail` or `instance`. `ProblemDetailsResponse` extends `JsonApiResponse`, so declare its code in `outputs()` like any other response; `ResponseBuilder::problemDetailsSchema()` is the schema. See the [example](/examples/problem-details).

## Only for clients that ask

`NegotiatingErrorHandler` keeps the 3.x format and sends problem details only when the `Accept` header lists `application/problem+json`, which helps while clients migrate:

```yaml
services:
	# replaces - Tomaj\NetteApi\Error\DefaultErrorHandler
	- Tomaj\NetteApi\Error\NegotiatingErrorHandler(
		Tomaj\NetteApi\Error\DefaultErrorHandler()
		Tomaj\NetteApi\Error\ProblemDetailsErrorHandler(typeBaseUri: 'https://api.example.com/problems/')
	)
```

`application/problem+json;q=0` does not count. `*/*`, `application/*` and `application/json` get the 3.x format. Every response of the error handler gets `Vary: Accept` (next to `Vary: Origin` from the [CORS allowlist](/guide/cors#allowed-origins)), so caches and CDNs keep the two formats apart.

The OpenAPI spec describes only one error format: the 3.x one by default, or problem details with `ResponseBuilder(problemDetails: true)`.

## OpenAPI

`ResponseBuilder(problemDetails: true)` adds a `ProblemDetails` schema and describes the `400`, `401` and `500` responses as `application/problem+json`. Pass it to the `OpenApiHandler` service you already have from [OpenAPI](/guide/openapi), next to its `initData`:

```yaml
services:
	# the same OpenApiHandler service, with specBuilder added
	- Tomaj\NetteApi\Handlers\OpenApiHandler(
		initData: [info: [title: 'Orders API']]
		specBuilder: Tomaj\NetteApi\OpenApi\SpecBuilder(
			Tomaj\NetteApi\OpenApi\PathBuilder(responseBuilder: Tomaj\NetteApi\OpenApi\ResponseBuilder(problemDetails: true))
		)
	)
```

Keep only one `OpenApiHandler` service: `addApi()` finds it by class name, and with two the OpenAPI endpoint fails with "Multiple services of type". Responses that a handler declares in `outputs()`, such as the `409` above, are described as `application/json`, because `JsonOutput` has no content type. See [OpenAPI](/guide/openapi).

## FAQ

### Should I use problem details?

Yes, in a new project. RFC 9457 gives every error the same machine-readable shape with `type`, `status`, `title` and `detail`, so clients can tell errors apart without parsing text. In an existing API with clients that read `message`, start with `NegotiatingErrorHandler` and switch once the clients send `Accept: application/problem+json`.

### Do I have to switch to problem details in 4.0?

No. `DefaultErrorHandler` stays the default and its bodies are the same as in 3.x. Register `ProblemDetailsErrorHandler` or `NegotiatingErrorHandler` when you want RFC 9457.

### Why does production send the validation errors but not the exception message?

Validation errors describe the client's own request and help to fix it. An exception message can contain hosts, SQL or paths, so it is sent only when `showErrorDetail()` is true and always logged to Tracy.

### Why did my 409 problem turn into a 500?

Outputs are validated in development and a code that is not in `outputs()` goes to `handleSchema()`. Add `new JsonOutput(409, Json::encode(ResponseBuilder::problemDetailsSchema()))` to the handler's outputs.

### What should the type URI point to?

A page that explains the problem to a developer. It does not have to resolve, but it must not change, because clients use it to tell problems apart. Without `typeBaseUri` the type is `about:blank`.

### Can I add my own members to a problem?

Yes, through `extensions` of `ProblemDetails`. They are sent next to the standard members; the five standard names are refused.
