# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

Planned for 4.0, developed on `master`. 3.x is maintained on the `3.x` branch.

### Changed (BC breaks)
* Renamed the unsafe CORS mode `'auto'` (any website can make credentialed requests) to `'reflect-unsafe'` (`CorsMode::ReflectUnsafe`) with the same behaviour plus `Vary: Origin, Referer`: `setCorsHeader('auto')` throws and `api: cors: mode: auto` fails the compilation, both with the allowlist to use instead and the new name (#186).
* `ApiPresenter` sends no CORS headers by default (`'off'` instead of `'*'`), with or without the DI extension; set `setCorsAllowedOrigins()` / `cors: allowedOrigins` or `'*'` explicitly. See `UPGRADE-4.0.md` (#186).
* `setCorsHeader()` throws `InvalidArgumentException` for a value that is neither a mode nor a `scheme://host[:port]` origin (before it was sent as the header, for example `'OFF'` or `'https://app.example.com/'`), and `setCorsAllowedOrigins()` errors say what is wrong and how to fix it (#186).
* Minimum PHP version is 8.4. CI runs on PHP 8.4 and 8.5; PHP 8.6 runs as an experimental job.
* Raised dependency minimums: `nette/application` ^3.2, `nette/di` ^3.2 and `nette/schema` ^1.3 (now required, for the DI extension), `nette/http` ^3.3, `tracy/tracy` ^2.10, `latte/latte` ^3.0.20, `league/fractal` ^0.21, `justinrainbow/json-schema` ^6.0.
* Removed deprecated `Tomaj\NetteApi\Misc\BearerTokenRepositoryInterface` and `Tomaj\NetteApi\Misc\StaticBearerTokenRepository`. Use `TokenRepositoryInterface` and `StaticTokenRepository`.
* All source files declare `strict_types=1`.
* Constructor-promoted and `readonly` properties, typed class constants (`ApiPresenter::DEFAULT_MASKED_LOG_HEADERS`, `MASKED_LOG_HEADER_VALUE`) and native type declarations replace docblock-only types. Subclasses that reassigned inherited properties, or overrode methods without matching types, must be updated.
* `QueryApiKeyAuthentication`, `HeaderApiKeyAuthentication` and `CookieApiKeyAuthentication` reject a non-string API key (e.g. `?api_key[]=x`) with `API key is not set` instead of throwing a `TypeError`.
* `BasicAuthentication` reads the user and password from the `Authorization: Basic` header (where `nette/http` puts `PHP_AUTH_USER`/`PHP_AUTH_PW`) instead of the request URL, which `nette/http` 3.2 and newer leaves empty, so it now works with the supported Nette versions. Credentials in the URL are ignored (#191).
* `JsonInputParam` and `JsonOutput` check their JSON schema in the constructor and throw `InvalidArgumentException` saying what is wrong and how to fix it, instead of accepting every value when the schema is not valid JSON or not an object (#191).
* `BasicAuthentication` constructor parameter `$autentications` was renamed to `$authentications` (affects named arguments only).
* `ApiDecider::getApi()` declares its `Api` return type natively.
* `BearerTokenAuthorization` treats a non-string `Authorization` header as missing.
* `OpenApi\SchemaRegistry` and `OpenApi\EndpointFilter` are `final`; `SpecBuilder::$pathBuilder` is typed as `PathBuilderInterface`.
* Removed deprecated `InputParam::setExample()` and `AbstractOutput::setExample()`. Use `addExample()`.
* `Validation\InputType` is a string-backed enum; its string constants (`InputType::INTEGER`, ...) are kept. It can no longer be instantiated or extended.
* Classes not meant for extension are `final`: `Api`, `ApiHolder`, `EndpointIdentifier`, `NoAuthorization`, `NoRateLimit`, `RateLimitResponse`, `IpDetector`, `StaticIpDetector`, `StaticTokenRepository`, `ConsoleRequest`, `ConsoleResponse`, `OpenApiTransform`, `ParamsProcessor`, `InputValidator`, `JsonSchemaValidator`, `ValidationResult`, `DebuggerConfigurator`, `EnvConfigurator`, `QueryConfigurator`. See `UPGRADE-4.0.md`.
* `TextApiResponse` throws `InvalidArgumentException` for arrays and non-`Stringable` objects instead of outputting `Array` with a warning (arrays) or failing with an `Error` (objects); non-string values in validation messages, IP headers, console fields and OpenAPI examples are converted to strings instead of raising warnings or `TypeError`.
* `Error\ErrorHandlerInterface` methods return `ResponseInterface` instead of `JsonApiResponse`, and the interface has two new methods, `handleNotFound(EndpointInterface)` and `handleRateLimit(RateLimitResponse)`. Own implementations must add them (#196).
* `ApiPresenter` builds the `404` for an unknown endpoint and the `429` for a reached rate limit (without an own error response) through the error handler. `DefaultErrorHandler` sends the same bodies as before, except that a `429` without a known retry time says only `Too many requests.` instead of `Retry after  seconds.` (#196).
* `Handlers\DefaultHandler` is `final`. `ApiPresenter` does not call its `handle()`, so a subclass could no longer change the `404`; do it in `ErrorHandlerInterface::handleNotFound()` (#196).
* Request logs are masked by default (#187): `ApiPresenter` replaces secret header values (`Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, names containing `token`, `secret` or `key`, `X-Auth-*`), secret query parameters in the logged URI (`api_key`, `token`, `access_token`, `password`, `signature`, `code`, ...) and the names used by the API key authentications with `***` before `ApiLoggerInterface::log()`. The DI extension's `logging: maskHeaders` defaults to `true`. Opt out with `setMaskedLogHeaders([])` / `setMaskedLogQueryParams([])` or `maskHeaders: false` / `maskQueryParams: false`. See `UPGRADE-4.0.md`.
* Rate limit headers follow the IETF draft: `RateLimit-Policy` (`"name";q=limit;w=window`) and `RateLimit` (`"name";r=remaining;t=reset`) replace `X-RateLimit-Limit` and `X-RateLimit-Remaining`; `Retry-After` stays on `429` (#195).
* `Api::getRateLimit()` and `ApiHolder::getRateLimit()` return `RateLimit\RateLimiterInterface`; a `RateLimitInterface` passed in is wrapped in `LegacyRateLimitAdapter` (#195).
* `nette/caching` ^3.2 and `psr/clock` ^1.0 are required, for the rate limit storages and time (#195).

### Added
* Log masking API (#187): `add`/`remove`/`get` methods for masked headers, header patterns and query parameters on `ApiPresenter`; `setLogMasking()` with `LogMaskMode::Full`, `Partial`, `Hash` (optional HMAC key) or `Omit`, or your own `LogValueMaskerInterface`; DI extension `logging: maskHeaders` / `maskQueryParams` as `true`, `false`, a list or `{names, add, remove, patterns}`, plus `masking` and `hashKey`. Wrong entries fail with what is wrong and how to fix it (a did-you-mean for typos), and a `logging:` key together with an `ApiPresenter` `setup:` call that changes the same list fails when the container is compiled, because the extension would replace the setup.
* `ApiPresenter::setCorsOriginValidator()` and `Misc\CorsOriginValidatorInterface` for origins not known in advance (for example customer domains in a database): a callable or the interface decides per request, the allowed origin is reflected with `Vary: Origin` and optional credentials. Only a valid `scheme://host[:port]` origin reaches the validator. Also `api: cors: originValidator` in the DI extension. Misconfigurations (not callable, wrong signature, a non-bool result, combined with `setCorsAllowedOrigins()` / `mode` / `allowedOrigins`) throw a message with the fix (#186).
* Rector config with a CI dry-run job, PHP_CodeSniffer config (PSR-12), Infection mutation testing in CI, lowest-dependency test job.
* `UPGRADE-4.0.md`.
* Enums `Validation\InputType`, `Params\ParamType` (`InputParam::getParamType()`) and `Presenters\CorsMode` (accepted by `ApiPresenter::setCorsHeader()`). Constructors and validators accept an enum case or a string.
* `#[\Override]` on all overriding methods.
* CodeQL and OpenSSF Scorecard workflows; Scorecard badge in README.
* OpenAPI builder interfaces (`SpecBuilderInterface`, `PathBuilderInterface`, `ParameterBuilderInterface`, `RequestBodyBuilderInterface`, `ResponseBuilderInterface`, `SecuritySchemeBuilderInterface`). `OpenApiHandler`, `SpecBuilder` and `PathBuilder` accept the interfaces.
* RFC 9457 Problem Details (#196): `Error\ProblemDetails`, `Response\ProblemDetailsResponse` (`application/problem+json`), `Error\ProblemDetailsErrorHandler` for every framework error (`400` with an `errors` list, `401`, `404`, `429` with `limit` and `retryAfter`, `500`; output schema errors as `outputErrors` only with error details on) and `Error\NegotiatingErrorHandler`, which sends problem details only to clients with `Accept: application/problem+json` and adds `Vary: Accept` to every error response. Opt-in, `DefaultErrorHandler` stays the default.
* `OpenApi\ResponseBuilder(problemDetails: true)` documents error responses with a `ProblemDetails` schema as `application/problem+json`; `ResponseBuilder::problemDetailsSchema()`.
* `examples/problem-details` and the Problem Details documentation page.
* Nette DI extension `DI\NetteApiExtension` (#200): the whole API in one `api:` neon section, with endpoints (`method`, `version`, `package`, `action`, `handler` as a class, a statement or `@service`), named `authorizations` and `rateLimits`, `defaults`, `errors` (`format: default|problemDetails|negotiating`, `typeBaseUri`, `handler`), `outputConfigurator`, `cors`, `logging.maskHeaders`, `ipDetector` and `logger`. Handlers are created lazily, and mistakes (unknown handler, unknown name, duplicate endpoint, unknown key) fail the container compilation. Services you register yourself are used instead of the extension's ones. Optional, the `services:` setup keeps working.
* `examples/di-extension` and the DI extension documentation page.
* The DI extension adds the `Api: Tomaj\NetteApi\Presenters\*Presenter` application mapping unless `application: mapping` already has an `Api` entry.
* Attribute endpoints (#198): `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]` and `#[Endpoint]` on a handler class (repeatable), `#[Authorization]` and `#[RateLimit]` for all endpoints of the class. `NetteApiExtension` reads them from autowired handler services and from classes in `api: discovery: paths` (optionally limited by `discovery: namespace`), at container compile time. They are checked together with `api: endpoints` (duplicates, unknown names).
* `examples/attributes` and the attribute endpoints documentation page.
* Detailed compile-time errors in `NetteApiExtension`: every message says what is wrong, where (the neon key, or the class and the attribute, with the bad value) and how to fix it, with an example and a "Did you mean" suggestion for misspelled names, services, namespaces and CORS modes. New checks for settings that silently did nothing: endpoint attributes on a method, `#[Authorization]`/`#[RateLimit]` without a route attribute, a non-autowired handler service with attributes that is not used, a `discovery: namespace` that matches no class, a quoted `'@service'` reference, a `@service` of the wrong type, `cors: mode` together with `allowedOrigins`, an invalid `cors: mode`, empty or invalid `allowedOrigins` (before: on the first request), and invalid `defaults` even when no endpoint uses them. Errors of the configuration schema (unknown key, wrong type, missing endpoint key, unknown `errors.format`) add the allowed keys or values and an example. Also checked now: a `@service` defined later by another extension, a class in a statement like `App\Nope(1)`, an HTTP method typo, a version, package or action with a space, `?` or `#`, an invalid `authorizations`/`rateLimits` name, a `typeBaseUri` that is not an absolute URI, an empty `cors: mode`, an empty `preflight` or `maskHeaders`, an invalid header name in them, a discovery path that is a file, a `discovery: namespace` without `paths`, and `logger` together with an own logger service.
* Rate limiting (#195): `FixedWindowLimiter`, `SlidingWindowLimiter` and `TokenBucketLimiter` behind `RateLimit\RateLimiterInterface`, combined by `RateLimiterChain`; keys by IP, identity, endpoint or a combination (`Key\RateLimitKeyInterface`); storages in memory, in the Nette cache (atomic on one server through a file lock in `%tempDir%`, waiting at most 3 seconds) or in Redis with one atomic Lua script (`Storage\RedisStorage` with ext-redis or Predis, keys `prefix{policy:key}` for Redis Cluster); own algorithms (`Algorithm\RateLimitAlgorithmInterface`, with `RedisScriptAlgorithmInterface` for Redis) and own storages (`Storage\RateLimitStorageInterface`, `KeyValueStorage`). `ApiPresenter` checks the limits before the authorization and, for identity keys, after it. A failing storage lets the request pass and logs a warning. Time comes from a `Psr\Clock\ClockInterface`. Intervals are from 1 second to 366 days. `IpKey` hashes values longer than an IP and other keys longer than 200 characters are hashed, the memory storage drops expired counters as it grows, and all algorithms keep the newest window or time of servers with skewed clocks. When the logger fails, the warning goes to `error_log`. Limits are at most 1 000 000 000, policy and `rateLimits` names are ASCII, the parts of a list key are escaped, and the presenter escapes policy names of own limiters in the headers.
* DI extension: named policies in `api: rateLimits` (`{algorithm, limit, interval, key, name}`), `api: rateLimiting` (`storage`, `redis`, `prefix`, `clock`), lists of policies and inline limits on endpoints and in `api: defaults`, `rateLimit: false`; inline limits always keep counters per endpoint (also with `name`), two named policies with the same `name` and an inline `name` equal to a named policy fail the compilation; `#[RateLimit]` takes a list or `limit`, `interval`, `algorithm`, `key` and `name` (#195).
* `examples/rate-limit` uses the built-in policies; `examples/auth`, `examples/di-extension` and `examples/attributes` no longer ship an own `FileRateLimit` (#195).

### Changed
* Dev tools: PHPUnit ^12 || ^13, PHPStan ^2.1 (level max, no baseline), Rector ^2.2, PHP_CodeSniffer ^4.0, Infection; `symfony/yaml` ^7.4.12 || ^8.0.12.
* `vendor/bin/phpunit` no longer requires `XDEBUG_MODE=coverage`; coverage reports are opt-in via `composer coverage`.

### Deprecated
* `RateLimit\RateLimitInterface` and its `check()`: it still works through `LegacyRateLimitAdapter` with an `E_USER_DEPRECATED` notice and is removed in 5.0. Implement `RateLimiterInterface` or use a built-in limiter (#195).

### Security
* `DefaultErrorHandler::handleAuthorizationException()` no longer sends the exception message to the client. It answers `500 Internal server error`, logs the exception to Tracy and adds the message as `detail` only when `showErrorDetail()` is true (#188).

## 3.6.0 - 2026-10-27

### Security
* `BasicAuthentication` and `StaticTokenRepository` compare passwords and tokens with `hash_equals()` (constant time) instead of `===` / `in_array()`. Results are unchanged, including for non-string passwords and numeric tokens, which never matched before and still don't.

### Added
* `ApiPresenter::setCorsAllowedOrigins()`: opt-in CORS allowlist based on the `Origin` header (with optional credentials and `Vary: Origin`). Existing `setCorsHeader()` values, including `'auto'`, behave exactly as before.
* README: warning about the `'auto'` CORS mode (any site can make credentialed requests) and a migration path to the allowlist.
* `ApiPresenter::setMaskedLogHeaders()`: opt-in masking of sensitive request header values (`Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key` by default, or a custom case-insensitive list) before they are passed to `ApiLoggerInterface`. Logging is unchanged unless enabled.
* `Tomaj\NetteApi\OpenApi` builders (`SpecBuilder`, `PathBuilder`, `ParameterBuilder`, `RequestBodyBuilder`, `ResponseBuilder`, `SecuritySchemeBuilder`) that can be extended and replaced via DI, and helpers `SchemaRegistry` and `EndpointFilter` created per generated spec. `OpenApiHandler` accepts an optional `SpecBuilder` as the last constructor argument; links are always created by the handler's `ApiLink`.
* Docs (EN + SK): OpenAPI builders and how to replace them via DI.

### Changed
* `OpenApiHandler` was split into the builders above; it now only selects the output format. The generated spec is unchanged (covered by snapshot tests).
* Schema definitions are collected per generated spec instead of per `OpenApiHandler` instance.

### Fixed
* OpenApiHandler: endpoints with version `0` are included in the spec of version `0`.
* OpenApiHandler: examples of outputs and JSON params that are not strings or arrays (e.g. `42`, `true`) no longer cause `TypeError`.
* OpenApiHandler: numeric definition names (e.g. `"definitions": {"200": ...}`) are kept in `components/schemas`, so `$ref` to them works.

## 3.5.0

### Added
* Added support for PHP 8.5

## 3.4.5

### Fixed
* OpenApiHandler: ErrorWrongInput can contain any message in the response.

## 3.4.4

### Fixed
* Multi value validation and transformation if expected type is not array but instead expects array of specific type. For example if expected type is integer and value is array of integers, validation and transformation will pass.
* Added check for required numeric values in InputValidator's transformType method.

## 3.4.3
### Fixed
* Invalid input type returned when value is empty in InputValidator

## 3.4.2
### Fixed
* Input validation before authorization check in ApiPresenter (causes issues with some authorization handlers that need to check input params)

## 3.4.1
### Fixed
* Don't overwrite global preflight handler if it is set and enableGlobalPreflight is called without parameter

## 3.4.0

### Added
* Allowed endpoints filter for OpenApiHandler

### Removed
* Support for justinrainbow/json-schema (causes issues with validating schema)

## 3.3.0

### Changed
* update minimum PHP to version 8.2 (currently still supported version) + update league/fractal library [BC]
* change Inject from PHPDoc format to native PHP attribute
* [Reverted] Remove BC addApi register method now requires ApiHandlerInterface as second parameter instead of string or service name

## 3.2.0

### Added
- Input type validation for InputParam
* Allow justinrainbow/json-schema to use v6 if necessary

## 3.1.0

### Changed
* Update Request body schema in OpenApiHandler
* Set null value when process empty file params, instead of false
* [BC] Update to php >= 8.3, support for 8.4
* [BC] addApi register method now requires ApiHandlerInterface as second parameter instead of string or service name

## 3.0.0

### Changed
* Support for semantic versioning api.
* [BC] DefaultHandler response code 404 instead 400
* [BC] Added Container to API Decider
* [BC] Output Configurator, Allows different methods for output configuration. Needs to be added to config services.
* [BC] Error handler, Allows for custom error handling of handle method. Needs to be added to config services.
* Query configurator rework

### Added
* CorsPreflightHandlerInterface - resolve multiple service registered handler error
* Lazy API handlers

## 2.12.0

### Added
* Button to copy `Body` content in api console
* Ability to disable schema validation and provide additional error info with get parameters. 

### Changed
* Handler tag wrapper has changed class from `btn` to `label`

### Fixed
* Allowed equal definitions in different handlers in OpenAPIHandler

## 2.11.0

### Added

* Support for symfony/yaml ^5.0 and ^6.0
* Support for JsonSerializable payload in JsonApiResponse
* Support for latte/latte ^3.0 (Added Latte extension for ApiLink)

## 2.10.1

### Fixed

* Fix *null* `$body` for php 8.0

## 2.10.0 - 2023-03-21

### Added

* Allow to set expiration to false in JsonApiResponse and XmlApiResponse
* Render body of response if there is a debug message: Tracy Debug Bar

### Fixed

* Fixed request url separator if url already contain some param

## 2.9.0 - 2022-11-10

### Added

* Support for generating examples for RawInputParam by OpenApiHandler
* PHP 8.1 support (`league/fractal` 0.20.1)
* PHP 8.2 support
* Support for `league/fractal` ~0.17 (Possible BC if you use Fractal classes, you have to update typehints)

### Changed

* If output doesn't match any output schema, error is just logged and output is returned in production mode

### Fixed

* Missing expiration params in XmlApiResponse
* Missing body request for PUT methost

## 2.8.0 - 2022-06-15

### Changed

* ApiLinkMacro now uses latte filter to avoid use presenter context (BC break in nette/application 3.1)

### Fixed

* BaseUrl in OpenApiHandler

## 2.7.0 - 2022-04-07

### Added

* RedirectResponse which implements ResponseInterface
* Available values now can have description in Console and OpenApiHandler. Just use associative array - keys are available values, values are their description

### Fixed

* Yaml format for OpenApiHandler is available only if symfony/yaml is installed
* RedirectOutput use new RedirectResponse
* Property with name `type` is now available in json schema

## 2.6.0 - 2021-10-08

### Updated

* Use relaxed TokenRepositoryInterface in BearerTokenAuthorization ([more info here](https://github.com/tomaj/nette-api/pull/112))

## 2.5.0 - 2021-09-17

### Fixed

* Empty console input fixed by adding checkbox for each parameter
* Handling wrong input for Get and Post InputParam
* File and cookie parameters in Open API handler
* Correct error shown when putting non-OutputInterface into ApiHandlerInterface::outputs()

## 2.4.0 - 2021-04-24

### Added

* User can set own form renderer for API console and own template file(s) for API console and for API listing

## 2.3.1 - 2021-04-13

### Fixed

* Added RateLimit to ApiDecider::addApi

## 2.3.0 - 2021-01-20

### Changed

* Form in API console is rendered with BootstrapVerticalRenderer instead of BootstrapRenderer (labels are over fields instead of left side)

### Added

* Added API key authentication (query, header, cookie) - see https://swagger.io/docs/specification/authentication/api-keys/

* Added missing strict_types declarations

### Fixed

* Open API handler warnings about unused security schemes

* ApiPresenter detailed error unit test

* Compatibility with latte 2.9.0

## 2.2.0 - 2020-08-27

### Added

* Added Basic authentication

## 2.1.0 - 2020-05-12

### Changed

* Rewritten ApiPresenter

### Added

* Added API rate limit 
* Added custom headers to API console
* Added field for timeout to API console
* OpenAPI handler
* Information about RESTful urls

### Fixed

* Fixed sending empty string in multi params
* UrlEncoding values sending through get param inputs
* Fixed static url part `/api/` in console
* Fixed generating urls in console for RESTful urls using ApiLink and EndpointInterface

## 2.0.1 - 2020-03-24

### Fixed

* Fixed return types for ConsoleRequest::processParam() and ConsoleResponse::getResponseHeaders()

## 2.0.0 - 2019-06-12

### Changed

* Updated nette libs to version 3.0.0 (BC break)
* Added typehints (BC break)
* Splitted InputParam to multiple subclasses (BC break)
* Removed type TYPE_POST_JSON_KEY (BC break)
* Wrong input now returns code 400 instead of 500 (BC break if somebody checks return code)
* Replaced handler information array triplet (endpoint, handler, authorization) with class Api (BC break for API console usage)
* Renamed some methods from ApiDecider (BC break)
* Pretty JSON output in API console - without escaping unicode and slashes

### Added

* Added type JsonInputParam with scheme as replacement for type TYPE_POST_JSON_KEY
* Detailed error for wrong input if debugger is enabled
* Added summary (short description), description, tags and deprecated flag for API handlers
* Added description, default value and example for input params
* Added output validator

### Removed

* Removed support for PHP 5.6, 7.0 and hhvm (BC Break)
* Removed deprecated class ApiResponse (BC Break)

## 1.17.0 - 2020-08-27

### Changed

* Detailed error message enabled only for non production mode

## 1.16.0 - 2019-06-19

### Added

* Added ApiLink Macro

## 1.15.0 - 2019-03-13

### Added

* Added possibility to set own scope to Manager
* Added JSON validation - if JSON is invalid, throw "wrong input" error instead of setting params to null

## 1.14.0 - 2018-08-02

### Added

* Added possibility to set own headers for CorsPreflightHandler
* Added expiration to JsonApiResponse

## 1.13.2 - 2018-03-23

### Fixed

* Removed names of components because of commit in nette/component-model (https://github.com/nette/component-model/commit/1fb769f4602cf82694941530bac1111b3c5cd11b)

## 1.13.1 - 2018-03-16

### Fixed

* Fixed console for POST_JSON_KEY(s)

## 1.13.0 - 2018-03-15

### Added

* New InputParam type - POST_JSON_KEY for parsing key from json data in post body
* Updated leaguge/fractal and other packages minor upgrade

## 1.12.0 - 2018-02-08

### Fixed

* Fixed console POST FIELDS output for integer values
* Fixed parsing headers on nginx - missing method `getallheaders`

## 1.11.0 - 2017-05-12

### Added

* Added possibility to send current PHPSESSID via API Web Console
* Removed Content-Type from CORS headers

## 1.10.0 - 2016-12-14

### Added

* Added X-Requested-With header to Cors handler

## 1.9.1 - 2016-11-28

### Fixed

* Added possibility to send empty fields via console request

## 1.9.0 - 2016-11-08

### Added

* Added support for PUT fields

## 1.8.1 - 2016-10-28

* Updated league/fractal library to 0.14.0

## 1.8.0 - 2016-09-28

### Added

* Added cors headers - Access-Control-Allow-Headers and Access-Control-Allow-Methods

## 1.7.0 - 2016-09-11

### Added

* Added default CORS preflight handler
* Added possibility to enable global preflight handler for all handlers with `enableGlobalPreflight()` on `ApiDecider` 

## 1.6.2 - 2016-07-22

### Fixed

* InputParam validation of multi params, if available values are defined

## 1.6.1 - 2016-07-05

### Changed

* End of support for php < 5.6
* Service of type Tomaj\NetteApi\Misc\IpDetectorInterface is no more required if service of type Tomaj\NetteApi\Logger\ApiLoggerInterface is not configured and used
* Service of type Tomaj\NetteApi\Logger\ApiLoggerInterface need not be named "apiLogger"

## 1.6.0 - 2016-05-11

### Added

* Added Content-Length header to Json and Xml responses

## 1.5.0 - 2016-05-04

### Changed

* ApiPresenter: getRequestDomain() returns port as well
* Updated JsonApiResponse - separate charset from content type as changed in nette/application 2.3.12
* Also change nette/application minimal version to 2.3.12

## 1.4.0 - 2016-04-01

### Changed

* changed rendering in API console when available values are set

### Fxed

* fixed negative values for response time in API web console

## 1.3.0 - 2016-02-17

### Added

* added tracy for error logging from ApiPresenter
* added tests for ApiPresenter

## 1.2.0 - 2016-02-11

### Added

* New ability to send new input types:
   - send RAW POST data
   - send COOKIES
   - send FILEs
   - (more info in readme section Inputs)
* all new types are available in test console for easy testing api calls

## 1.1.0 - 2016-01-15

### Added

* Added CORS support to ApiPresenter. Available options:
   - 'auto' - send back header Access-Control-Allow-Origin with domain that made request
   - '*' - send header with '*' - this will work fine if you dont need to send cookies via ajax calls to api with jquery $.ajax with xhrFields: { withCredentials: true } settings
   - 'off' - will not send any CORS header
   - other - any other value will be send in Access-Control-Allow-Origin header
* Rewritten few internal functions

## 1.0.1 - 2015-01-07

### Fixed

* Fixed parsing array variables from GET and POST when using multi InputParam

## 1.0.0 - 2016-01-06

### Added

First version that can be used for api. Contains

* Authorization
* Handling api request
* Logging
* UI Control for api listing
* UI Control for web console to test api endpoints
* Api versioning
