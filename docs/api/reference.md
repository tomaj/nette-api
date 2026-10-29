---
title: Reference
description: Every public class and interface of Nette API 3.x by namespace, with a one line description and a link to the guide page.
---

# Reference

All classes live under `Tomaj\NetteApi`.

## Core

| Class | What it does | Guide |
| --- | --- | --- |
| `ApiDecider` | registry of endpoints: `addApi()`, `getApi()`, `getApis()`, `enableGlobalPreflight()` | [Architecture](/guide/architecture) |
| `Api` | one registered endpoint: endpoint, handler, authorization, rate limit | [Architecture](/guide/architecture) |
| `ApiHolder` | used inside `ApiDecider`: how it stores an endpoint before its handler is created; the handler can still be a class or service name | [ApiDecider](/guide/api-decider#three-ways-to-pass-a-handler) |
| `EndpointIdentifier` / `EndpointInterface` | method, version, package, action | [Handlers](/guide/handlers#registering-a-handler) |
| `Presenters\ApiPresenter` | runs a request step by step | [ApiPresenter pipeline](/guide/presenter) |

## Handlers

| Class | What it does | Guide |
| --- | --- | --- |
| `Handlers\ApiHandlerInterface`, `BaseHandler` | your endpoints | [Handlers](/guide/handlers) |
| `Handlers\OpenApiHandler` | OpenAPI 3.0 document | [OpenAPI](/guide/openapi) |
| `Handlers\ApiListingHandler` | JSON list of the endpoints in its own version | [Handlers](/guide/handlers#handlers-that-ship-with-the-library) |
| `Handlers\AlwaysOkHandler`, `EchoHandler` | health check and test endpoints | [Handlers](/guide/handlers#handlers-that-ship-with-the-library) |
| `Handlers\DefaultHandler` | `404 Unknown api endpoint` | [Architecture](/guide/architecture) |
| `Handlers\CorsPreflightHandler(Interface)` | answers `OPTIONS` | [CORS](/guide/cors#preflight-requests) |

## Params and validation

| Class | What it does | Guide |
| --- | --- | --- |
| `Params\GetInputParam`, `PostInputParam`, `PutInputParam`, `CookieInputParam`, `FileInputParam`, `RawInputParam`, `JsonInputParam` | one input each | [Params](/guide/params) |
| `Params\InputParam`, `ParamInterface`, `ParamsProcessor` | the base class and the processing | [Params](/guide/params) |
| `Validation\InputValidator`, `JsonSchemaValidator`, `InputType` (enum) | value checks | [Params](/guide/params#types) |
| `ValidationResult\ValidationResult(Interface)` | the result of a check | [Params](/guide/params) |

## Responses and outputs

| Class | What it does | Guide |
| --- | --- | --- |
| `Response\ResponseInterface` | what every response implements | [Outputs](/guide/outputs#responses) |
| `Response\JsonApiResponse`, `TextApiResponse`, `XmlApiResponse`, `RedirectResponse` | what `handle()` returns | [Outputs](/guide/outputs#responses) |
| `Output\JsonOutput`, `RedirectOutput`, `AbstractOutput`, `OutputInterface` | declared outputs | [Outputs](/guide/outputs#outputs) |
| `Output\Configurator\ConfiguratorInterface`, `EnvConfigurator`, `QueryConfigurator`, `DebuggerConfigurator` | when to check outputs and show details | [Outputs](/guide/outputs#when-outputs-are-validated) |
| `Error\ErrorHandlerInterface`, `DefaultErrorHandler` | error responses | [Error handling](/guide/errors) |
| `Error\ProblemDetailsErrorHandler`, `NegotiatingErrorHandler`, `ProblemDetails`, `Response\ProblemDetailsResponse` | RFC 9457 errors, 4.0.0 | [Problem Details](/guide/problem-details) |

## Security

| Class | What it does | Guide |
| --- | --- | --- |
| `Authorization\NoAuthorization`, `BearerTokenAuthorization`, `HeaderApiKeyAuthentication`, `QueryApiKeyAuthentication`, `CookieApiKeyAuthentication`, `BasicAuthentication` | built-in authorization | [Authorization](/guide/authorization) |
| `Authorization\ApiAuthorizationInterface`, `TokenAuthorization` | your own | [Authorization](/guide/authorization#your-own-authorization) |
| `Misc\TokenRepositoryInterface`, `StaticTokenRepository` | tokens and their IPs | [Authorization](/guide/authorization#tokens-and-ip-restrictions) |
| `Misc\IpDetectorInterface`, `IpDetector`, `StaticIpDetector` | the client IP | [Authorization](/guide/authorization#tokens-and-ip-restrictions) |
| `RateLimit\RateLimitInterface`, `RateLimitResponse`, `NoRateLimit` | rate limiting | [Rate limiting](/guide/rate-limit) |

## Logging, links and console

| Class | What it does | Guide |
| --- | --- | --- |
| `Logger\ApiLoggerInterface` | request log | [Logging](/guide/logging) |
| `Link\ApiLink`, `ApiLinkExtension`, `ApiLinkNode` | URLs and the `{apiLink}` tag | [Links](/guide/links) |
| `Component\ApiListingControl`, `ApiConsoleControl`, `ApiConsoleFormFactoryInterface`, `DefaultApiConsoleFormFactory` | web console | [Web console](/guide/console) |
| `Misc\ConsoleRequest`, `ConsoleResponse` | the console's cURL request | [Web console](/guide/console) |
| `Misc\CorsOriginAllowlist` | internal (`@internal`), used by `setCorsAllowedOrigins()`; may change in 4.0 | [CORS](/guide/cors#allowed-origins) |
| `Misc\OpenApiTransform` | helpers for OpenAPI | [OpenAPI](/guide/openapi) |

## FAQ

### Which classes are final?

`Presenters\ApiPresenter`, `Error\DefaultErrorHandler`, the Latte classes `Link\ApiLinkExtension` and `Link\ApiLinkNode`, and the internal `Misc\CorsOriginAllowlist` are `final`; wrap them instead of extending. See [Error handling](/guide/errors#your-own-error-handler).

Since 4.0 these are `final` too: `Api`, `ApiHolder`, `EndpointIdentifier`, `Authorization\NoAuthorization`, `RateLimit\NoRateLimit`, `RateLimit\RateLimitResponse`, `Misc\IpDetector`, `Misc\StaticIpDetector`, `Misc\StaticTokenRepository`, `Misc\ConsoleRequest`, `Misc\ConsoleResponse`, `Misc\OpenApiTransform`, `Params\ParamsProcessor`, `Validation\InputValidator`, `Validation\JsonSchemaValidator`, `ValidationResult\ValidationResult`, the output configurators `DebuggerConfigurator`, `EnvConfigurator` and `QueryConfigurator`, and `OpenApi\SchemaRegistry` and `OpenApi\EndpointFilter`. Implement the matching interface (`IpDetectorInterface`, `TokenRepositoryInterface`, `ApiAuthorizationInterface`, `RateLimitInterface`, `ConfiguratorInterface`, …) instead of extending them. Handlers, params, outputs, responses, authorizations, the OpenAPI builders, `ApiDecider`, `ApiLink`, the console components and `DefaultApiConsoleFormFactory` stay open for extension. The exception is `Handlers\DefaultHandler`, which is `final`; customize the `404` in the error handler's `handleNotFound()`.

### Where are the method signatures?

In the source on [GitHub](https://github.com/tomaj/nette-api/tree/master/src); the guide pages show the ones you use.

### Which classes are deprecated?

None. `setExample()` on params and outputs was removed in 4.0; use `addExample()`. The old `StaticBearerTokenRepository` and `BearerTokenRepositoryInterface` were removed in 4.0. Use `StaticTokenRepository` and `TokenRepositoryInterface`, which work the same way.

### Is there a DI extension?

No, everything is registered as plain services in NEON, see [Getting started](/guide/getting-started).

### What does a full project look like?

See the [examples](/examples/).
