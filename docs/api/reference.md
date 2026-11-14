---
title: Reference
description: The public classes and interfaces of Nette API by namespace, with a one line description and a link to the guide page.
---

# Reference

All classes live under `Tomaj\NetteApi`.

## Core

| Class | What it does | Guide |
| --- | --- | --- |
| `ApiDecider` | registry of endpoints: `addApi()`, `getApi()`, `matchPath()`, `getApis()`, `enableGlobalPreflight()` | [Architecture](/guide/architecture) |
| `Api` | one registered endpoint: endpoint, handler, authorization, rate limit | [Architecture](/guide/architecture) |
| `ApiHolder` | used inside `ApiDecider`: how it stores an endpoint before its handler is created; the handler can still be a class or service name | [ApiDecider](/guide/api-decider#three-ways-to-pass-a-handler) |
| `EndpointIdentifier` / `EndpointInterface` | method, version, package, action | [Handlers](/guide/handlers#registering-a-handler) |
| `Presenters\ApiPresenter` | runs a request step by step | [ApiPresenter pipeline](/guide/presenter) |
| `DI\NetteApiExtension` | registers the services and endpoints from the `api:` neon section (4.0.0) | [DI extension](/guide/di-extension) |
| `Attribute\Get`, `Post`, `Put`, `Patch`, `Delete`, `Endpoint` | endpoint attributes on a handler class (4.0.0) | [Attribute endpoints](/guide/attributes) |
| `Attribute\Authorization`, `Attribute\RateLimit` | authorization and rate limit of the attribute endpoints of a class (4.0.0) | [Attribute endpoints](/guide/attributes#authorization-and-rate-limit) |
| `DI\EndpointDefinition` | one endpoint for `NetteApiExtension::addEndpoint()` from another extension (4.0.0) | [DI extension](/guide/di-extension#can-another-extension-add-endpoints) |

## Handlers

| Class | What it does | Guide |
| --- | --- | --- |
| `Handlers\ApiHandlerInterface`, `BaseHandler`, `TypedHandler` | your endpoints; `TypedHandler` takes a DTO | [Handlers](/guide/handlers), [Typed input](/guide/typed-input) |
| `Handlers\OpenApiHandler` | OpenAPI 3.0 document | [OpenAPI](/guide/openapi) |
| `Handlers\ApiListingHandler` | JSON list of the endpoints in its own version | [Handlers](/guide/handlers#handlers-that-ship-with-the-library) |
| `Handlers\AlwaysOkHandler`, `EchoHandler` | health check and test endpoints | [Handlers](/guide/handlers#handlers-that-ship-with-the-library) |
| `Handlers\DefaultHandler` | `404 Unknown api endpoint` | [Architecture](/guide/architecture) |
| `Handlers\CorsPreflightHandler(Interface)` | answers `OPTIONS` | [CORS](/guide/cors#preflight-requests) |

## Params and validation

| Class | What it does | Guide |
| --- | --- | --- |
| `Params\GetInputParam`, `PostInputParam`, `PutInputParam`, `PathInputParam`, `HeaderInputParam`, `BodyInputParam`, `CookieInputParam`, `FileInputParam`, `RawInputParam`, `JsonInputParam` | one input each | [Params](/guide/params) |
| `Params\InputParam`, `ParamInterface`, `ParamsProcessor` | the base class and the processing | [Params](/guide/params) |
| `Validation\InputValidator`, `JsonSchemaValidator`, `InputType` (enum) | value checks | [Params](/guide/params#types) |
| `ValidationResult\ValidationResult(Interface)` | the result of a check | [Params](/guide/params) |

## Typed input (4.0.0)

| Class | What it does | Guide |
| --- | --- | --- |
| `Input\Query`, `Path`, `Header`, `Body`, `File` | attributes that say where a DTO property is read from | [Typed input](/guide/typed-input#source-attributes) |
| `Input\Consumes`, `Input\FileUpload` | restricts the accepted body media types; the typed upload value | [Body, files and content types](/guide/typed-input-body) |
| `Input\Cast`, `CasterInterface`, `OpenApiCasterInterface`, `CastException` | a custom conversion of one property | [Conversion](/guide/typed-input-conversion#custom-conversions-with-cast) |
| `Input\DateFormat`, `UnixTimestamp`, `Split` | built-in casters | [Conversion](/guide/typed-input-conversion#built-in-casters-and-a-czech-date) |
| `Validation\Range`, `Length`, `Pattern`, `Email` | built-in constraints | [Constraints](/guide/typed-input-conversion#constraints) |
| `Validation\ConstraintInterface`, `ConstraintContext` | your own constraints | [Custom constraints](/guide/typed-input-conversion#custom-constraints) |
| `Params\MappedInputParam` | the `InputParam` generated for one DTO property | [Typed input](/guide/typed-input) |
| `Input\RequestInput`, `Params\PathValues` | request state of a typed handler, reset in long-running workers | [Long-running workers](/guide/typed-input#long-running-workers) |

The other classes of the `Input` namespace are the machinery behind these (compiler, mapper, metadata, schema and conversion helpers). They are not listed and are not meant to be extended or called directly.

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
| `RateLimit\RateLimiterInterface`, `RateLimitResult`, `FixedWindowLimiter`, `SlidingWindowLimiter`, `TokenBucketLimiter`, `AlgorithmLimiter`, `RateLimiterChain`, `NoRateLimit` | rate limiting (4.0.0) |
| `RateLimit\Algorithm\RateLimitAlgorithmInterface`, `Storage\RateLimitStorageInterface`, `Key\RateLimitKeyInterface`, `Redis\RedisClientInterface`, `Storage\KeyValueStorage`, `Authorization\AuthorizationIdentityInterface` | own algorithms, storages, keys, Redis clients and the identity of a caller (4.0.0) |
| `RateLimit\RateLimitInterface`, `RateLimitResponse` | the 3.x rate limit, deprecated, removed in 5.0 | [Rate limiting](/guide/rate-limit) |

## Logging, links and console

| Class | What it does | Guide |
| --- | --- | --- |
| `Logger\ApiLoggerInterface` | request log | [Logging](/guide/logging) |
| `Link\ApiLink` (`link()`), `ApiLinkExtension`, `ApiLinkNode` | URLs and the `{apiLink}` tag | [Links](/guide/links) |
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

Yes, since 4.0.0: `DI\NetteApiExtension` configures the whole API from one `api:` neon section, see [DI extension](/guide/di-extension). Registering plain services in NEON, as in [Getting started](/guide/getting-started), keeps working.

### What does a full project look like?

See the [examples](/examples/).
