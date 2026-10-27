---
title: Error handling
description: How Nette API turns exceptions, wrong input, failed authorization and broken outputs into JSON with DefaultErrorHandler, and how to wrap it in your own handler.
---

# Error handling

`ApiPresenter` never builds error responses itself. It calls the `ErrorHandlerInterface` service, usually `DefaultErrorHandler`.

## The interface and the default answers

| Method | Called when | DefaultErrorHandler answers |
| --- | --- | --- |
| `handle(Throwable, array $params)` | `handle()` threw | `500 Internal server error`, logs to Tracy |
| `handleInputParams(array $errors)` | params are invalid | `400 wrong input` |
| `handleSchema(array $errors, array $params)` | the output check failed | `500 Internal server error`, logs to Tracy |
| `handleAuthorization(ApiAuthorizationInterface, array $params)` | `authorized()` is false | `401` with `getErrorMessage()` |
| `handleAuthorizationException(Throwable, array $params)` | `authorized()` threw | `500` with the exception message |

All of them return a `JsonApiResponse` with `status: error` and `message`.

## Error details

`DefaultErrorHandler(ConfiguratorInterface)` adds `detail` when the configurator's `showErrorDetail()` is true: the exception message for `500`, the errors per field for `400`, the schema errors for a broken output.

```json
{"status":"error","message":"Internal server error","detail":"Database connection refused"}
```

Which configurator to use is described in [Outputs](/guide/outputs#when-outputs-are-validated). In production details should be off.

## Your own error handler

`DefaultErrorHandler` is `final`, so wrap it. The [logging example](/examples/logging-errors) turns one exception into `503` and leaves the rest to the default:

<<< @/../examples/logging-errors/app/AppErrorHandler.php

```yaml
services:
	defaultErrorHandler:
		factory: Tomaj\NetteApi\Error\DefaultErrorHandler
		autowired: false
	- App\AppErrorHandler(@defaultErrorHandler)
```

`autowired: false` keeps a single `ErrorHandlerInterface` for autowiring.

## FAQ

### Why does production answer 400 without saying which field is wrong?

`DefaultErrorHandler` hides `detail` for every code when `showErrorDetail()` is false, including `400`. `DefaultErrorHandler` is `final`, so implement `ErrorHandlerInterface`, delegate every method to the wrapped `DefaultErrorHandler` as in [Your own error handler](#your-own-error-handler), and in `handleInputParams()` return `new JsonApiResponse(400, ['status' => 'error', 'message' => 'wrong input', 'detail' => $errors])`.

### How do I send a 404 for a missing record?

Return it from the handler: `new JsonApiResponse(404, [...])`. Error handlers are for unexpected failures. See [Handlers](/guide/handlers).

### Where do I see the exception?

In the Tracy log directory, `exception.log` and an HTML file per exception. The [logging example](/examples/logging-errors) checks it.

### Can authorization errors carry their own status code?

Your error handler decides; `handleAuthorization()` gets the authorization object and can read anything from it. See [Authorization](/guide/authorization).

### Is a broken output an error in production?

Only if the configurator validates outputs there. With `EnvConfigurator` and `APP_ENV=production` the response is sent unchecked.
