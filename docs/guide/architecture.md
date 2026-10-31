---
title: Architecture
description: How ApiPresenter handles a request in Nette API 3.x - endpoint lookup, rate limit, params, authorization, the handler, output schema check and logging.
---

# Architecture

Every API request goes to one presenter, `Tomaj\NetteApi\Presenters\ApiPresenter`. It asks `ApiDecider` which endpoint matches, runs the checks in a fixed order and returns what the handler or the error handler produced.

<Diagram name="architecture-1" alt="Request, ApiDecider, rate limit, authorization, param error reporting (400), handler, output schema check, response and logger, with 429, 401 and 400 exits and a 404 from DefaultHandler (logged); every response goes back to the client" />

## The parts

| Part | Class or interface | Job |
| --- | --- | --- |
| Presenter | `ApiPresenter` | Runs every step below. It is `final`. |
| Endpoint list | `ApiDecider` | `addApi()` registers endpoints, `getApi()` finds the one for a request. |
| Endpoint | `EndpointIdentifier` | Method, version, package and optional action. |
| Handler | `ApiHandlerInterface`, usually `BaseHandler` | Declares params and outputs, does the work. |
| Authorization | `ApiAuthorizationInterface` | Says whether the request may run. |
| Rate limit | `RateLimiterInterface` | Optional, per endpoint. |
| Errors | `ErrorHandlerInterface`, `DefaultErrorHandler` | Turns failures into JSON responses. |
| Output checks | `ConfiguratorInterface` | Decides whether outputs are validated and error details shown. |
| Logger | `ApiLoggerInterface` | Optional, found in the DI container. |

## The order of the steps

In short: CORS headers, endpoint lookup (`404`), rate limit (`429`), params are read, authorization (`401`), param errors (`400`), the handler, the output check (`500`) and logging. Params are read before authorization, but their errors are reported only after it, so a caller without access gets `401` and no details about the params.

The full order, with the code and the exit of every step, is on one page: [ApiPresenter pipeline](/guide/presenter).

<Diagram name="architecture-2" alt="Sequence of a POST request: authorization, then param errors (read before it) and the handler, each with its error branch through the error handler" />

::: warning Not every response is logged
The rate limit, authorization and param error steps return early, so `429`, `401`, `400` and the `500` of a failing authorization never reach the logger in 3.x. `404`, handler responses and handler or output errors are logged.
:::

## FAQ

### Can I replace ApiPresenter with my own presenter?

Not by extending it, the class is `final`. You can configure it (`setCorsHeader()`) and swap its dependencies: the error handler, the configurator and the logger. See [Error handling](/guide/errors).

### Why does a request with a wrong token and wrong params answer 401, not 400?

Authorization runs before param errors are reported. See step 6 above and [Authorization](/guide/authorization).

### Is the handler created for every endpoint on every request?

No. When you pass a class name to `addApi()`, the handler is taken from the DI container only when its endpoint is called. See [Handlers](/guide/handlers#registering-a-handler).

### What does the response of an unknown endpoint look like?

`404` with `{"status":"error","message":"Unknown api endpoint"}`. The [hello world example](/examples/hello-world) tests it.

### Where do I see all the classes in one list?

In the [Reference](/api/reference).
