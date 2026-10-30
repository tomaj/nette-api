# Upgrade from 3.x to 4.0

4.0 is developed on `master`. The 3.x line keeps receiving fixes on the `3.x` branch (`composer require tomaj/nette-api:^3.6`).

## Requirements

* PHP 8.4 or newer.
* `nette/application` ^3.2, `nette/http` ^3.3, `tracy/tracy` ^2.10, `latte/latte` ^3.0.20, `league/fractal` ^0.21, `justinrainbow/json-schema` ^6.0, `nette/di` ^3.2, `nette/schema` ^1.3.

## CORS: 'auto' renamed to 'reflect-unsafe', no CORS headers by default

`'auto'` sent the origin of the `Referer` header with `Access-Control-Allow-Credentials: true`, so any website could call the API with the user's cookies and read the response (#186). In 4.0:

* `'auto'` is renamed to `'reflect-unsafe'` (`CorsMode::Auto` to `CorsMode::ReflectUnsafe`) with the same behaviour plus `Vary: Origin, Referer` (so shared caches do not reuse the reflected origin for another site), so the risk is visible in the config. `setCorsHeader('auto')` throws `InvalidArgumentException`, and `api: cors: mode: auto` fails the container compilation; the message shows the allowlist to use instead and the new name.
* `ApiPresenter` sends no `Access-Control-Allow-Origin` until you configure it (default `'off'`, before `'*'`), the same as with the DI extension.
* `setCorsHeader()` accepts only `'off'`, `'*'`, `'reflect-unsafe'` (or a `CorsMode` case) or one origin like `'https://app.example.com'`. Other values (`'OFF'`, a trailing slash, a list) were sent as a header browsers reject; now they throw `InvalidArgumentException` with the valid values, the same check as `api: cors: mode`.
* `setCorsAllowedOrigins()` errors name the method and the fix; do not match the old wording (`CORS allowlist must contain at least one origin`, `Invalid CORS origin ...`).

List the origins that call the API from a browser:

```neon
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			# was: - setCorsHeader('auto')
			- setCorsAllowedOrigins(['https://app.example.com', 'http://localhost:3000'], true)
```

or with the extension `api: cors: {allowedOrigins: [https://app.example.com], allowCredentials: true}`. Pass `true` / `allowCredentials` only when the browser sends cookies or uses `withCredentials`. If you relied on the old default `'*'`, add `setCorsHeader('*')` (or `cors: {mode: '*'}`). To keep the old behaviour anyway (an API without cookies, or during the migration), use `setCorsHeader('reflect-unsafe')` or `cors: {mode: reflect-unsafe}`; any website can still read responses made with the user's cookies.

If the origins are not known in advance (for example customer domains in a database), use the new `setCorsOriginValidator()` with a callable or a `CorsOriginValidatorInterface` (`api: cors: {originValidator: App\Api\CustomerOrigins}`) instead of reflecting every origin. See [dynamic origins](https://tomaj.github.io/nette-api/guide/cors#dynamic-origins).

## BasicAuthentication reads the Authorization header

`BasicAuthentication` takes the user and password from the `Authorization: Basic` header, which `nette/http` fills from `PHP_AUTH_USER` and `PHP_AUTH_PW`. In 3.x it read the request URL, which `nette/http` 3.2 and newer leaves empty, so it rejected every request there. If you wrote your own class for that (like `BasicCredentialsAuthorization` in the auth example), you can use `BasicAuthentication` again. Credentials written into the URL (`https://user:pass@host/`) are no longer used.

## JSON schemas are checked when defined

`new JsonInputParam($key, $schema)` and `new JsonOutput($code, $schema)` throw `InvalidArgumentException` when `$schema` is not valid JSON or is not a JSON object (or `true`/`false`). Before, such a schema accepted every value. The message names the parameter or output and shows a valid schema. Validation messages come from `justinrainbow/json-schema` 6, so their wording may differ from 5.x; do not match them exactly in clients.

## Removed deprecated bearer token classes

| Removed | Replacement |
|---|---|
| `Tomaj\NetteApi\Misc\BearerTokenRepositoryInterface` | `Tomaj\NetteApi\Misc\TokenRepositoryInterface` |
| `Tomaj\NetteApi\Misc\StaticBearerTokenRepository` | `Tomaj\NetteApi\Misc\StaticTokenRepository` |

The interfaces have the same methods, so in most cases renaming the class is enough:

```php
// 3.x
new BearerTokenAuthorization(new StaticBearerTokenRepository(['token' => '*']), $ipDetector);

// 4.0
new BearerTokenAuthorization(new StaticTokenRepository(['token' => '*']), $ipDetector);
```

## Stricter types

* Every file declares `strict_types=1`.
* Properties are natively typed; many are constructor-promoted and `readonly`. If you extend library classes, do not reassign these properties and match the native parameter and return types in overridden methods.
* `ApiPresenter::DEFAULT_MASKED_LOG_HEADERS` and `ApiPresenter::MASKED_LOG_HEADER_VALUE` are typed constants. A subclass that overrides them must keep the type.

## OpenAPI builders

* `Tomaj\NetteApi\OpenApi\SchemaRegistry` and `Tomaj\NetteApi\OpenApi\EndpointFilter` are `final` (`EndpointFilter` is also `readonly`). Use composition instead of extending them.
* `SpecBuilder::$pathBuilder` is typed as `PathBuilderInterface`. Constructors of `OpenApiHandler`, `SpecBuilder` and `PathBuilder` accept the builder interfaces, so passing the concrete builders still works.

## Removed setExample()

`InputParam::setExample()` and `AbstractOutput::setExample()` were removed. Use `addExample()`:

```php
// 3.x
$param->setExample('abc');

// 4.0
$param->addExample('default', 'abc');
```

## Enums

* `Tomaj\NetteApi\Validation\InputType` is a string-backed enum (`InputType::Integer`, ...). The old constants (`InputType::INTEGER` = `'integer'`, ...) are kept, so existing code keeps working. It can no longer be instantiated or extended.
* The `$valueType` constructor argument of `InputParam` and its subclasses is typed `InputType|string|null`; passing another type (e.g. an int) now throws `TypeError`. `InputValidator::validate()` / `transformType()` accept an `InputType` case or a string.
* New `Params\ParamType` enum and `InputParam::getParamType()`; `getType()` and the `TYPE_*` constants are unchanged.
* New `Presenters\CorsMode` enum (`Any`, `Off`, `ReflectUnsafe`); `setCorsHeader()` accepts it or a string. See [CORS](#cors-auto-renamed-to-reflect-unsafe-no-cors-headers-by-default) for the renamed `'auto'`.

## Final classes

These classes are now `final`: `Api`, `ApiHolder`, `EndpointIdentifier`, `Authorization\NoAuthorization`, `RateLimit\NoRateLimit`, `RateLimit\RateLimitResponse`, `Misc\IpDetector`, `Misc\StaticIpDetector`, `Misc\StaticTokenRepository`, `Misc\ConsoleRequest`, `Misc\ConsoleResponse`, `Misc\OpenApiTransform`, `Params\ParamsProcessor`, `Validation\InputValidator`, `Validation\JsonSchemaValidator`, `ValidationResult\ValidationResult`, `Output\Configurator\DebuggerConfigurator`, `EnvConfigurator` and `QueryConfigurator`. Implement the interface (`IpDetectorInterface`, `TokenRepositoryInterface`, `ApiAuthorizationInterface`, `RateLimitInterface`, `ConfiguratorInterface`, ...) instead of extending them. Extension points stay open: handlers (except `Handlers\DefaultHandler`, see [Error handlers](#error-handlers)), params, outputs, responses, authorizations, OpenAPI builders, `ApiDecider`, `ApiLink`, console components and `DefaultApiConsoleFormFactory`.

Overriding methods are marked with `#[\Override]`.

## Value conversion

* `TextApiResponse` with an array or a non-`Stringable` object now throws `InvalidArgumentException` (the request ends with 500) instead of outputting `Array` (arrays) or failing with an `Error` (objects). Return a `JsonApiResponse` for structured data.
* Non-string values in validation messages, IP headers, console fields and OpenAPI examples are converted to strings (scalars directly, other values as JSON) instead of raising warnings or `TypeError`.

## Renamed constructor parameter

`BasicAuthentication::__construct()` parameter `$autentications` is now `$authentications`. This only matters if you pass it as a named argument.

## API key authentication

`QueryApiKeyAuthentication`, `HeaderApiKeyAuthentication` and `CookieApiKeyAuthentication` now return `false` with the error message `API key is not set` when the key is not a string (for example `?api_key[]=x`). Previously this ended with a `TypeError`. `BearerTokenAuthorization` treats a non-string `Authorization` header as missing.

## Error handlers

`ErrorHandlerInterface` methods return `ResponseInterface` instead of `JsonApiResponse`, and the interface has two new methods. An own implementation must add them; a wrapper can delegate to `DefaultErrorHandler`:

```php
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\RateLimit\RateLimitResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

public function handleNotFound(EndpointInterface $endpoint): ResponseInterface
{
    return $this->inner->handleNotFound($endpoint);
}

public function handleRateLimit(RateLimitResponse $rateLimit): ResponseInterface
{
    return $this->inner->handleRateLimit($rateLimit);
}
```

Returning `JsonApiResponse` from the old methods still satisfies the interface. Code that calls an error handler and needs `JsonApiResponse` methods such as `getPayload()` must check the type.

`ApiPresenter` asks the error handler for the `404` of an unknown endpoint and for the `429` of a reached rate limit, unless `RateLimitResponse` has its own error response. With `DefaultErrorHandler` the bodies are unchanged: `{"status":"error","message":"Unknown api endpoint"}` and `{"status":"error","message":"Too many requests. Retry after N seconds."}`, or only `Too many requests.` when the rate limit does not know the retry time.

`Tomaj\NetteApi\Handlers\DefaultHandler` is `final`. `ApiPresenter` no longer calls its `handle()`, so a subclass that changed the `404` would be silently ignored. Move that code to `handleNotFound()` of your error handler:

```php
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

public function handleNotFound(EndpointInterface $endpoint): ResponseInterface
{
    return new JsonApiResponse(404, ['status' => 'error', 'message' => 'No endpoint ' . $endpoint->getUrl()]);
}
```

## Exceptions from authorization are not sent to the client

`DefaultErrorHandler::handleAuthorizationException()` answered with the exception message, which could reveal database hosts or internal paths. It now answers `{"status":"error","message":"Internal server error"}`, logs the exception to Tracy and adds the message as `detail` only when the configurator's `showErrorDetail()` is true, the same as `handle()`. Clients that parsed the message must read the log instead.

## Problem Details (optional, recommended)

4.0 can send errors as RFC 9457 `application/problem+json`, the recommended format for new projects. Nothing changes until you register `Tomaj\NetteApi\Error\ProblemDetailsErrorHandler` or `NegotiatingErrorHandler`. See the [documentation](https://tomaj.github.io/nette-api/guide/problem-details) and `examples/problem-details`.

## DI extension (optional)

4.0 adds `Tomaj\NetteApi\DI\NetteApiExtension`, which configures the API from one `api:` neon section. Nothing changes until you add it to `extensions:`; the `services:` setup from 3.x keeps working, and the extension uses an `ApiDecider`, `ApiPresenter`, error handler, output configurator or IP detector you already registered. `nette/di` ^3.2 and `nette/schema` ^1.3 are now required dependencies. The extension also adds the `Api: Tomaj\NetteApi\Presenters\*Presenter` application mapping; an `Api` entry you already have in `application: mapping` is kept.

**CORS default with the extension:** when `api: cors: mode` is not set, the presenter sends no CORS headers (`'off'`), the same as `ApiPresenter` on its own in 4.0. If browsers on other origins call your API, set `cors: allowedOrigins: [...]` (recommended) or `cors: mode: '*'` to keep the 3.x behaviour. A `setCorsHeader()` call on an `ApiPresenter` you register in `services:` is kept. See the [documentation](https://tomaj.github.io/nette-api/guide/di-extension) and `examples/di-extension`.

## Attribute endpoints (optional)

With the DI extension you can write `#[Get(1, 'users')]`, `#[Authorization]` and `#[RateLimit]` from `Tomaj\NetteApi\Attribute` on a handler class instead of an `api: endpoints:` entry. The extension reads them from autowired handler services and from `api: discovery: paths`. An autowired handler you already register in `services:` that happens to carry such an attribute becomes an endpoint, so do not add the attributes to handlers that are also listed in `api: endpoints:` (the compilation fails with a duplicate endpoint). See the [documentation](https://tomaj.github.io/nette-api/guide/attributes) and `examples/attributes`.
