---
title: DI extension
description: 'Configure Nette API in one neon section with NetteApiExtension: endpoints, authorization, rate limits, CORS, logging and error format, checked when the container is compiled.'
---

# DI extension

::: tip Since 4.0.0
`Tomaj\NetteApi\DI\NetteApiExtension` is new in 4.0.0. The `services:` setup from [Getting started](/guide/getting-started) keeps working, the extension is optional.
:::

The extension registers everything the API needs and reads the endpoints from one `api:` section. Instead of a list of `addApi()` calls with long class names you write what the endpoint is:

```yaml [config/api.neon]
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

api:
	endpoints:
		- {method: GET, version: 1, package: hello, handler: App\Api\HelloHandler}
```

That is a complete API. `GET /api/v1/hello` calls `App\Api\HelloHandler` from [Getting started](/guide/getting-started#_4-write-the-handler). You still need the API route from [step 2 of Getting started](/guide/getting-started#_2-route-the-api-to-apipresenter). The `application: mapping` for the `Api` module is added by the extension; if you already have one, yours is kept.

Every mistake is found when Nette compiles the container, not when a client calls the endpoint: an unknown handler class, a typo in an authorization name, the same endpoint twice. The error message says which endpoint and what to change.

## What the extension registers

| Service | Class | Change it with |
| --- | --- | --- |
| `api.decider` | `ApiDecider` | `api.endpoints` |
| `api.presenter` | `ApiPresenter` | `api.cors`, `api.logging` |
| `api.outputConfigurator` | `EnvConfigurator` | `api.outputConfigurator` |
| `api.errorHandler` | `DefaultErrorHandler` | `api.errors` |
| `api.ipDetector` | `IpDetector` | `api.ipDetector` |
| `api.logger` | only when you set it | `api.logger` |

With an empty `api:` section you get no endpoints, no CORS headers, the 3.x error format and error details unless `APP_ENV=production`. That is the `services:` setup from Getting started, except that Getting started uses `DebuggerConfigurator` (set `outputConfigurator: debugger` to get it) and the presenter there sends `Access-Control-Allow-Origin: *` (set `cors: {mode: '*'}` to get it).

::: info Your own services win
If the `services:` section already has an `ApiDecider`, `ApiPresenter`, error handler, output configurator or IP detector, the extension uses yours and adds its configuration to it. So you can switch to the extension one endpoint at a time, see [Moving from services](#moving-from-services).
:::

## Endpoints

Each item of `api.endpoints` is one endpoint:

| Key | Required | Meaning |
| --- | --- | --- |
| `method` | yes | HTTP method, `GET`, `post`, ... (case does not matter) |
| `version` | yes | `1`, `2`, `'2.1'` without the `v`; the URL is `/api/v{version}/...`. Write a version with a dot in quotes, neon reads `2.10` as the number `2.1` |
| `package` | yes | the first part of the URL after the version, without a slash |
| `action` | no | the second part, `/api/v1/users/detail` has the action `detail`, without a slash |
| `handler` | yes | the handler, see below |
| `authorization` | no | who may call it, see [Authorization](#authorization-and-rate-limits) |
| `rateLimit` | no | how often, see [Authorization](#authorization-and-rate-limits) |

```yaml
api:
	endpoints:
		# GET /api/v1/users
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler}
		# GET /api/v1/users/detail?id=5
		- {method: GET, version: 1, package: users, action: detail, handler: App\Api\UserDetailHandler}
		# POST /api/v1/users
		- {method: POST, version: 1, package: users, handler: App\Api\UserCreateHandler}
		# GET /api/v2.1/users
		- {method: GET, version: '2.1', package: users, handler: App\Api\V2\UsersListHandler}
```

Endpoints are registered in this order, which is also the order in the [endpoint listing](/guide/openapi) and in the [web console](/guide/console).

The same method, version, package and action twice stops the compilation:

```
Endpoint GET /v1/users is defined twice: in api.endpoints[0] and in api.endpoints[3]. Keep only one of them.
```

### The handler

You can write the handler in three ways:

```yaml
api:
	endpoints:
		# 1. a class: the extension creates it with autowiring
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler}

		# 2. a class with arguments: a statement, like in services:
		- {method: GET, version: 1, package: export, handler: App\Api\ExportHandler(%tempDir%/exports)}

		# 3. a service from the services: section
		- {method: GET, version: 1, package: reports, handler: @reportsHandler}

services:
	reportsHandler: App\Api\ReportsHandler(%tempDir%/reports)
```

- **A class** becomes a service, so its constructor gets other services by autowiring, the same as any other service. One class used by more endpoints is one service. When the `services:` section already has an autowired service of exactly that class, the extension uses it.
- **A statement** `Class(arguments)` is a new service only for this endpoint.
- **`@name`** is the service with that name. Write it without quotes, `'@name'` in quotes is a plain string in neon.

Handlers are created only when their endpoint is called, so an API with a hundred endpoints does not create a hundred objects on every request.

## Authorization and rate limits

Name the authorizations and rate limits once, then use the names in endpoints:

```yaml
parameters:
	adminPassword: change-me
	apiTokens:
		secret-token: '*'

api:
	authorizations:
		token: Tomaj\NetteApi\Authorization\BearerTokenAuthorization(Tomaj\NetteApi\Misc\StaticTokenRepository(%apiTokens%))
		admin: Tomaj\NetteApi\Authorization\BasicAuthentication([admin: %adminPassword%])
	rateLimits:
		strict: App\FileRateLimit(storageDir: %tempDir%, limit: 2)

	endpoints:
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler, authorization: token}
		- {method: DELETE, version: 1, package: users, handler: App\Api\UserDeleteHandler, authorization: admin, rateLimit: strict}
```

`authorization` and `rateLimit` of an endpoint accept:

| Value | Meaning |
| --- | --- |
| a name from `api.authorizations` / `api.rateLimits` | that object, shared by all endpoints that use the name |
| `@service` | a service from `services:` |
| a class or `Class(arguments)` | a new object for this endpoint |
| `false` | none, even when there is a default |
| nothing | the default from `api.defaults`, else no authorization and no rate limit |

### Defaults

Most APIs protect almost every endpoint. Set the authorization and the rate limit once in `api.defaults` for all endpoints, then change them only where an endpoint needs something else:

```yaml
api:
	authorizations:
		token: Tomaj\NetteApi\Authorization\BearerTokenAuthorization(Tomaj\NetteApi\Misc\StaticTokenRepository(%apiTokens%))
		admin: Tomaj\NetteApi\Authorization\BasicAuthentication([admin: %adminPassword%])
	rateLimits:
		normal: App\Api\MyRateLimit(limit: 100)
		strict: App\Api\MyRateLimit(limit: 5)
	defaults:
		authorization: token
		rateLimit: normal
	endpoints:
		# token and normal, from the defaults
		- {method: GET, version: 1, package: users, handler: App\Api\UsersListHandler}
		# admin instead of token, the rate limit stays normal
		- {method: DELETE, version: 1, package: users, handler: App\Api\UserDeleteHandler, authorization: admin}
		# public and with the strict rate limit
		- {method: POST, version: 1, package: login, handler: App\Api\LoginHandler, authorization: false, rateLimit: strict}
		# public and without a rate limit
		- {method: GET, version: 1, package: hello, handler: App\Api\HelloHandler, authorization: false, rateLimit: false}
```

Each of the two keys is decided on its own: an endpoint without the key uses the default, a name or a class replaces it, `false` turns it off. `api.defaults` itself does not take `false`, leave the key out when you want no default. Use a name from `api.authorizations` / `api.rateLimits` as the default, so all endpoints share one object; a class name there creates a new object for every endpoint.

A class of the wrong type stops the compilation too, for example a handler class used as an authorization:

```
Class 'App\Api\HelloHandler' in api.authorizations.token must implement Tomaj\NetteApi\Authorization\ApiAuthorizationInterface.
```

A typo in a name stops the compilation and lists the names you defined:

```
Unknown value 'tokne' in the authorization of endpoint GET /v1/users (api.endpoints[0]). Use a name defined in the api section, @serviceName, a class name or a statement like App\MyClass(arguments). Defined names: 'token'.
```

Read [Authorization](/guide/authorization) and [Rate limiting](/guide/rate-limit) for the classes you can use.

## Errors

`api.errors.format` picks the [error format](/guide/errors):

```yaml
api:
	errors:
		# default: {"status":"error","message":"..."}, the 3.x format
		# problemDetails: RFC 9457 application/problem+json for every error
		# negotiating: problem details only for clients that send Accept: application/problem+json
		format: problemDetails
		typeBaseUri: https://api.example.com/problems/
```

`typeBaseUri` is the start of the problem `type` (`https://api.example.com/problems/not-found`), see [Problem Details](/guide/problem-details). It works only with `problemDetails` and `negotiating`.

Your own error handler goes to `handler` instead:

```yaml
api:
	errors:
		handler: App\Api\MyErrorHandler
```

## Output configurator

`api.outputConfigurator` decides whether error responses show the exception message and whether outputs are validated, see [Outputs](/guide/outputs):

| Value | Class |
| --- | --- |
| nothing, or `env` | `EnvConfigurator`: details unless `APP_ENV=production` |
| `debugger` | `DebuggerConfigurator`: details when Tracy is in development mode |
| `query` | `QueryConfigurator`: details with `?error_detail=1` |
| a class, `Class(arguments)` or `@service` | your own `ConfiguratorInterface` |

```yaml
api:
	outputConfigurator: Tomaj\NetteApi\Output\Configurator\EnvConfigurator('APP_ENV', 'production')
```

## CORS

```yaml
api:
	cors:
		# the origins that may call the API from a browser
		allowedOrigins: [https://app.example.com, 'http://localhost:3000']
		# send Access-Control-Allow-Credentials: true to them (cookies)
		allowCredentials: true
		# answer OPTIONS for every endpoint
		preflight: true
```

| Key | Same as | Default |
| --- | --- | --- |
| `mode` | `setCorsHeader()`: `'*'`, `'off'`, `'auto'` or one origin | `'off'` |
| `allowedOrigins` | `setCorsAllowedOrigins()` | not set |
| `allowCredentials` | the second argument of `setCorsAllowedOrigins()`, needs `allowedOrigins` | `false` |
| `preflight` | `enableGlobalPreflight()`: `true`, or a map of headers for `CorsPreflightHandler` | `false` |

```yaml
api:
	cors:
		mode: 'off'
		preflight:
			Access-Control-Allow-Headers: [Content-Type, Authorization]
			Access-Control-Max-Age: '600'
```

Without `mode` the extension sends no CORS headers, unlike `ApiPresenter` on its own, which sends `*`. A browser app on another origin needs `allowedOrigins` or `mode`. If you register your own `ApiPresenter` with `setCorsHeader()` in `services:`, the extension keeps that value. The [CORS](/guide/cors) page explains what each mode sends.

## Logging

```yaml
api:
	logger: App\Api\DatabaseLogger
	logging:
		# true: Authorization, Cookie, X-Api-Key, ... are logged as ***
		maskHeaders: true
```

`maskHeaders` also takes your own list, `[Authorization, X-Secret]`. See [Logging](/guide/logging).

## IP detector

```yaml
api:
	ipDetector: App\Api\ProxyIpDetector
```

The default `IpDetector` reads `REMOTE_ADDR`.

## Configuration reference

All keys with their defaults:

```yaml
api:
	outputConfigurator: null   # env | debugger | query | Class | Class(args) | @service
	ipDetector: null           # Class | Class(args) | @service, default IpDetector
	logger: null               # Class | Class(args) | @service, default no logger
	errors:
		format: null           # default | problemDetails | negotiating
		typeBaseUri: null      # with problemDetails or negotiating
		handler: null          # Class | Class(args) | @service, instead of format
	cors:
		mode: null             # '*' | 'off' | auto | an origin, default 'off'
		allowedOrigins: null   # list of origins
		allowCredentials: false
		preflight: false       # true | map of headers
	logging:
		maskHeaders: false     # true | list of headers
	authorizations: []         # name: Class | Class(args) | @service
	rateLimits: []             # name: Class | Class(args) | @service
	defaults:
		authorization: null    # a name | Class | Class(args) | @service
		rateLimit: null        # a name | Class | Class(args) | @service
	endpoints: []              # {method, version, package, action, handler, authorization, rateLimit}
```

An unknown key is an error, so a typo such as `cors: {origins: ...}` does not silently do nothing.

## Moving from services

The extension uses the services you already have. Add it, then move one endpoint at a time from `addApi()` to `api.endpoints`:

```yaml
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
	- Tomaj\NetteApi\Output\Configurator\DebuggerConfigurator
	- Tomaj\NetteApi\Error\DefaultErrorHandler
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			# not moved yet
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'old'), App\Api\OldHandler(), Tomaj\NetteApi\Authorization\NoAuthorization())

api:
	endpoints:
		# moved
		- {method: GET, version: 1, package: hello, handler: App\Api\HelloHandler}
```

Both endpoints end up in your `apiDecider`. When all of them are moved, delete the `services:` lines.

::: warning CORS changes when you add the extension
Your `apiPresenter` sent `Access-Control-Allow-Origin: *` because that is its default. With the extension and no `api: cors`, it sends no CORS headers. If browsers on other origins call the API, add `cors: allowedOrigins: [...]`, or `cors: mode: '*'` to keep what you had. A `setCorsHeader()` in your presenter's `setup:` is kept.
:::

Setting an option and having the same service in `services:` is a conflict, because the extension would not know which one you mean:

```
You set api.errors, but service 'errorHandler' of type Tomaj\NetteApi\Error\ErrorHandlerInterface is registered in the services section too. Remove one of them.
```

## FAQ

### Do I have to use the extension?

No. The `services:` configuration from 3.x works without any change. The extension is a shorter way to write the same thing.

### Why does my endpoint answer 404?

Check the API route from [Getting started](/guide/getting-started#_2-route-the-api-to-apipresenter). The extension registers the presenter and maps the `Api` module to it, but the route is yours. If your `application: mapping` has its own `Api` entry, the extension keeps it, so it must point to the namespace of the `ApiPresenter` you use (`Tomaj\NetteApi\Presenters\*Presenter` unless you have your own subclass).

### Why does the browser say the CORS header is missing?

With the extension, CORS is off until you configure it. Add the origins of your frontend to `api: cors: allowedOrigins`, or set `mode`, see [CORS](#cors).

### Why does `handler: '@myHandler'` say the service does not exist?

In neon a quoted `'@myHandler'` is a plain string that starts with `@`. Write `@myHandler` without quotes.

### Can a handler get other services in its constructor?

Yes. A handler written as a class is created with autowiring, like any service. See the `UserRepository` in the [example](/examples/di-extension).

### How do I make one endpoint public when there is a default authorization?

Set `authorization: false` on that endpoint.

### Can I set the authorization and the rate limit for all endpoints and change them for one?

Yes. Put them in `api.defaults`, then write `authorization` or `rateLimit` only on the endpoints that differ: a name to use another one, `false` for none. See [Defaults](#defaults).

### How do I see what the extension generated?

Open the compiled container in `temp/cache/nette.configurator/`. The `createServiceApi__decider()` method has an `addApi()` call for each endpoint.

### Can another extension add endpoints?

Yes, call `addEndpoint()` on the API extension in its `loadConfiguration()`. The duplicate check covers these endpoints too:

```php
use Nette\DI\CompilerExtension;
use Tomaj\NetteApi\DI\EndpointDefinition;
use Tomaj\NetteApi\DI\NetteApiExtension;

final class ReportsExtension extends CompilerExtension
{
    public function loadConfiguration(): void
    {
        foreach ($this->compiler->getExtensions(NetteApiExtension::class) as $api) {
            $api->addEndpoint(new EndpointDefinition(
                method: 'GET',
                version: 1,
                package: 'reports',
                action: null,
                handler: \App\Api\ReportsHandler::class,
                authorization: null, // null: api.defaults, false: none
                rateLimit: null,
                source: 'ReportsExtension', // shown in error messages
            ));
        }
    }
}
```

Register it after the API extension in `extensions:`.

### Where is a complete project?

In [examples/di-extension](/examples/di-extension). Its `e2e.sh` test runs in CI.
