# Upgrade from 3.x to 4.0

4.0 is developed on `master`. The 3.x line keeps receiving fixes on the `3.x` branch (`composer require tomaj/nette-api:^3.6`).

## Requirements

* PHP 8.4 or newer.
* `nette/application` ^3.2, `nette/http` ^3.3, `tracy/tracy` ^2.10, `latte/latte` ^3.0.20, `league/fractal` ^0.21, `justinrainbow/json-schema` ^6.0.

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
* New `Presenters\CorsMode` enum (`Auto`, `Any`, `Off`); `setCorsHeader()` accepts it or a string. `'auto'` is unchanged.

## Final classes

These classes are now `final`: `Api`, `ApiHolder`, `EndpointIdentifier`, `Authorization\NoAuthorization`, `RateLimit\NoRateLimit`, `RateLimit\RateLimitResponse`, `Misc\IpDetector`, `Misc\StaticIpDetector`, `Misc\StaticTokenRepository`, `Misc\ConsoleRequest`, `Misc\ConsoleResponse`, `Misc\OpenApiTransform`, `Params\ParamsProcessor`, `Validation\InputValidator`, `Validation\JsonSchemaValidator`, `ValidationResult\ValidationResult`, `Output\Configurator\DebuggerConfigurator`, `EnvConfigurator` and `QueryConfigurator`. Implement the interface (`IpDetectorInterface`, `TokenRepositoryInterface`, `ApiAuthorizationInterface`, `RateLimitInterface`, `ConfiguratorInterface`, ...) instead of extending them. Extension points stay open: handlers, params, outputs, responses, authorizations, OpenAPI builders, `ApiDecider`, `ApiLink`, console components and `DefaultApiConsoleFormFactory`.

Overriding methods are marked with `#[\Override]`.

## Value conversion

* `TextApiResponse` with an array or a non-`Stringable` object now throws `InvalidArgumentException` (the request ends with 500) instead of outputting `Array` (arrays) or failing with an `Error` (objects). Return a `JsonApiResponse` for structured data.
* Non-string values in validation messages, IP headers, console fields and OpenAPI examples are converted to strings (scalars directly, other values as JSON) instead of raising warnings or `TypeError`.

## Renamed constructor parameter

`BasicAuthentication::__construct()` parameter `$autentications` is now `$authentications`. This only matters if you pass it as a named argument.

## API key authentication

`QueryApiKeyAuthentication`, `HeaderApiKeyAuthentication` and `CookieApiKeyAuthentication` now return `false` with the error message `API key is not set` when the key is not a string (for example `?api_key[]=x`). Previously this ended with a `TypeError`. `BearerTokenAuthorization` treats a non-string `Authorization` header as missing.

## Unchanged

* `ApiPresenter::setCorsHeader('auto')` still works. Prefer `setCorsAllowedOrigins()` for new code.
