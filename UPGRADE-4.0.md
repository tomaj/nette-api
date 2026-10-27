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

## Renamed constructor parameter

`BasicAuthentication::__construct()` parameter `$autentications` is now `$authentications`. This only matters if you pass it as a named argument.

## API key authentication

`QueryApiKeyAuthentication`, `HeaderApiKeyAuthentication` and `CookieApiKeyAuthentication` now return `false` with the error message `API key is not set` when the key is not a string (for example `?api_key[]=x`). Previously this ended with a `TypeError`. `BearerTokenAuthorization` treats a non-string `Authorization` header as missing.

## Unchanged

* `ApiPresenter::setCorsHeader('auto')` still works. Prefer `setCorsAllowedOrigins()` for new code.
* `InputParam::setExample()` is still available (deprecated); use `addExample()`.
