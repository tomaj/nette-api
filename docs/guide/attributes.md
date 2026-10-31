---
title: Attribute endpoints
description: 'Declare Nette API endpoints with PHP attributes on the handler class: #[Get], #[Post], #[Put], #[Patch], #[Delete], #[Endpoint], #[Authorization] and #[RateLimit], found by the DI extension.'
---

# Attribute endpoints

::: tip Since 4.0.0
The attributes in `Tomaj\NetteApi\Attribute` are new in 4.0.0. They need the [DI extension](/guide/di-extension).
:::

With attributes you write the URL of an endpoint right on the handler class. There is no endpoint list in neon to keep in sync with the code:

```php [app/Api/UsersListHandler.php]
namespace App\Api;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

#[Get(1, 'users')]
final class UsersListHandler extends BaseHandler
{
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(200, ['users' => []]);
    }
}
```

`#[Get(1, 'users')]` means `GET /api/v1/users`. The neon config only says where the handlers are:

```yaml [config/api.neon]
extensions:
	api: Tomaj\NetteApi\DI\NetteApiExtension

api:
	discovery:
		paths: [%appDir%/Api]
```

When Nette compiles the container, the extension finds every class in `app/Api` with an endpoint attribute and registers it, exactly as if you had written it in `api: endpoints:`. The compiled container is the same: one `addApi()` call per endpoint and a lazy handler.

## The endpoint attributes

| Attribute | Endpoint |
| --- | --- |
| `#[Get(1, 'users')]` | `GET /api/v1/users` |
| `#[Get(1, 'users', 'detail')]` | `GET /api/v1/users/detail` |
| `#[Post(1, 'users')]` | `POST /api/v1/users` |
| `#[Put(1, 'users', 'update')]` | `PUT /api/v1/users/update` |
| `#[Patch(1, 'users')]` | `PATCH /api/v1/users` |
| `#[Delete(1, 'users')]` | `DELETE /api/v1/users` |
| `#[Endpoint('HEAD', 2, 'users')]` | `HEAD /api/v2/users`, any method |

The arguments are the same as in `api: endpoints:`: `version`, `package` and an optional `action`. The version can be a number or a string like `'1.1'`. Named arguments work too: `#[Get(version: 1, package: 'users', action: 'detail')]`.

### One handler, more endpoints

The attributes can repeat. Every one is a separate endpoint with the same handler service:

```php
#[Get(1, 'users')]
#[Get(2, 'users')]
#[Post(1, 'users', 'search')]
final class UsersListHandler extends BaseHandler
```

## Authorization and rate limit

`#[Authorization]` and `#[RateLimit]` apply to all endpoints of the class. The value is the same as in neon: a name from `api: authorizations:` / `api: rateLimits:`, `@service`, a class name, or `false` for none. `#[RateLimit]` also takes a list of names, `#[RateLimit(['perIp', 'perUser'])]`, or an inline limit, `#[RateLimit(limit: 5, interval: '1 minute')]` with optional `algorithm`, `key` and `name`; see [Rate limiting](/guide/rate-limit).

```php
use Tomaj\NetteApi\Attribute\Authorization;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Attribute\RateLimit;

#[Post(1, 'users')]
#[Authorization('token')]
#[RateLimit('strict')]
final class UserCreateHandler extends BaseHandler
```

```yaml
api:
	authorizations:
		token: Tomaj\NetteApi\Authorization\BearerTokenAuthorization(Tomaj\NetteApi\Misc\StaticTokenRepository(%apiTokens%))
	rateLimits:
		strict: {algorithm: fixedWindow, limit: 5, interval: 1 minute}
	defaults:
		authorization: token
```

Without the attribute the endpoint gets `api: defaults:`. So with `defaults: authorization: token` you don't write `#[Authorization('token')]` anywhere and mark only the public endpoints:

```php
#[Get(1, 'hello')]
#[Authorization(false)]
final class HelloHandler extends BaseHandler
```

::: warning Without a default, an endpoint is public
When `api: defaults: authorization:` is not set, a handler without `#[Authorization]` needs no authorization at all, the same as in `api: endpoints:`. Set the default and mark public endpoints with `#[Authorization(false)]`, so a forgotten attribute means a protected endpoint, not an open one.
:::

::: info A statement is not possible in an attribute
PHP attributes take only constant values, so `#[Authorization('App\MyAuth(%secret%)')]` does not work. Give the authorization a name in `api: authorizations:` and use the name.
:::

## Where the extension looks

### Directories

`discovery: paths:` is a list of directories. The extension reads all PHP files in them, including subdirectories. Each class with an endpoint attribute gets its own service, created with autowiring, so its constructor can ask for other services.

`discovery: namespace:` limits the search to one namespace. Other classes in the directories are not even loaded:

```yaml
api:
	discovery:
		paths: [%appDir%]
		namespace: App\Api
```

The classes must be loadable by the composer autoloader. A class that is not fails the compilation with its file name.

In debug mode a new file in one of the directories (also in a subdirectory) or a changed handler file rebuilds the container, the same as a change in a neon file.

### Registered services

The extension also reads the attributes of every autowired handler you register in `services:`, without `discovery:`. This is the way for a handler that needs a scalar argument:

```yaml
services:
	- App\Api\HelloHandler('Hello')

api:
	discovery:
		paths: [%appDir%/Api]
```

`HelloHandler` has `#[Get(1, 'hello')]`. It is in `app/Api` too, but the extension uses the registered service and does not create a second one.

A service with `autowired: false` is skipped, the same rule as for `handler: App\Api\HelloHandler` in `endpoints:`. A class from `paths` then gets its own service. To use the non-autowired service, list its endpoints in `api: endpoints:` with `handler: @name` (without quotes).

## Attributes and neon together

You can mix `api: endpoints:` and attributes. All endpoints go through the same checks: the same method, version, package and action twice fails the compilation, and the message says where both are:

```
Endpoint GET /v1/users is defined twice: in api.endpoints[0] and in #[Get] on App\Api\UsersListHandler. Remove one of them, or change the method, version, package or action of one so the URLs differ.
```

The order is: endpoints from `api: endpoints:` first, then the attribute endpoints sorted by class name.

## Errors

Each message says what to do, usually with an example. The most common ones:

| Message starts with | Cause |
| --- | --- |
| `Class App\Api\Foo has an endpoint attribute, but it does not implement ...` | The attribute is on a class that is not a handler. |
| `Class App\Api\Foo has an endpoint attribute, but it is abstract` | Attributes are not inherited, put them on each concrete handler. |
| `Attribute #[Get] on method App\Api\Foo::handle() is ignored` | Endpoint attributes work only on the class. |
| `Class App\Api\Foo has #[Authorization] but no route attribute` | `#[Authorization]` and `#[RateLimit]` need a route attribute on the same class. |
| `Endpoint in #[Get] on App\Api\Foo has an invalid version 'v1'` | The `v` is added to the URL, write `#[Get(1, 'users')]`. |
| `Invalid endpoint attribute on App\Api\Foo (#[RateLimit] has both a rate limit name and options of an inline limit ...)` | A name cannot be combined with `limit`, `interval`, `algorithm`, `key` or `name`, they would be ignored. Use one or the other. |
| `Invalid endpoint attribute on App\Api\Foo (Attribute "...\Authorization" must not be repeated)` | `#[Authorization]` and `#[RateLimit]` can be on a class only once. |
| `Class App\Foo (...) found by api.discovery cannot be loaded` | The class extends or implements a class that does not exist. Fix the `use` or the name, or limit the search with `namespace:`. |
| `Class App\Foo (...) found by api.discovery cannot be autoloaded.` | Fix the namespace or the autoload section of `composer.json`, or limit the search with `namespace:`. |
| `api.discovery.paths[0]: directory '...' does not exist.` | A typo in the path. Use `%appDir%` for a path relative to the application. |
| `api.discovery.namespace '...' matches no class in api.discovery.paths` | A typo in the namespace, the message lists the namespaces found. |
| `Services 'a' and 'b' are both of class ...` | Register the handler only once. |
| `Service 'foo' (class ...) has endpoint attributes, but it is registered with autowired: false` | The attributes of a non-autowired service are ignored, use it as `handler: @foo` or remove `autowired: false`. |
| `Unknown authorization 'tokne' in #[Authorization] on ...` | The name is not in `api: authorizations:`. |

## Configuration reference

```yaml
api:
	discovery:
		# directories with handler classes, empty by default (only registered services are read)
		paths: [%appDir%/Api]
		# only classes in this namespace, null by default (all classes)
		namespace: App\Api
```

| PHP attribute | Arguments |
| --- | --- |
| `Get`, `Post`, `Put`, `Patch`, `Delete` | `version`, `package`, `action = null` |
| `Endpoint` | `method`, `version`, `package`, `action = null` |
| `Authorization` | `authorization`: name, `@service`, class name, or `false` |
| `RateLimit` | `rateLimit`: name, list of names, `@service`, class name, or `false`; or `limit`, `interval`, `algorithm = 'slidingWindow'`, `key = 'ip'`, `name = null` |

## Design decisions

These are deliberate limits. Each one has a backlog issue with the pros, the cons and the conditions under which it would be added. If you need one, write there.

### Registered services are always read

The extension reads the attributes of every autowired handler service, there is no `discovery: services: false`.

- **Why:** an attribute on a class says "expose this endpoint", so it works wherever the handler is registered. It is also the way to give a handler scalar constructor arguments.
- **Why not a switch:** one more option for a need nobody has yet. Today you can register the service with `autowired: false` or remove the attribute.
- **When it would make sense:** a library ships handlers with attributes that applications want to register under other URLs. See [#226](https://github.com/tomaj/nette-api/issues/226).

### Authorization and rate limit only for the whole class

There is no `#[Get(1, 'users', authorization: false)]`.

- **Why:** one handler usually has one access rule. Two rules in one class are a sign it does two jobs, and two handlers are easier to review for security. Two levels (class, `api: defaults:`) are simpler than three.
- **Why not per endpoint:** the route attributes get longer and a forgotten argument is harder to spot.
- **When it would make sense:** handlers that cannot be split reasonably, or repeated requests. See [#227](https://github.com/tomaj/nette-api/issues/227).

### Custom authorization only by its neon name

`#[Authorization('token')]` refers to `api: authorizations: token`. PHP does not allow a statement with arguments in an attribute, and a class name (`::class`) is not a documented, supported form.

- **Why:** secrets and constructor arguments belong to neon. One name is configured in one place and used by attributes and `api: endpoints:` alike.
- **Why not `::class`:** it works only for classes without scalar arguments, so there would be two ways to do the same thing, and security settings would be split between neon and code.
- **When it would make sense:** many authorizations without arguments, where the neon name only repeats the class. See [#228](https://github.com/tomaj/nette-api/issues/228).

## FAQ

### Do I have to use attributes?

No. `api: endpoints:` and the `services:` setup from 3.x keep working. Attributes are a way to keep the URL next to the code.

### Why is my handler not found?

Check that the directory is in `discovery: paths:`, the class is in the `namespace:` (when you set one) and the class has an endpoint attribute. A handler without one is skipped on purpose.

### Is the directory read on every request?

No. The extension reads the classes once, when the container is compiled. The compiled container has only `addApi()` calls, the same as with `api: endpoints:`.

### Can the handler get services in its constructor?

Yes, the handler is created with autowiring. For a scalar argument register the handler in `services:`, see [Registered services](#registered-services).

### Does a subclass get the attributes of its parent?

No. PHP does not inherit attributes, also not from a trait or an interface. Write the attributes on the final handler class. An attribute on an abstract class, an interface, a trait or an enum fails the compilation.

### Can I set the authorization for one endpoint of a class?

No, `#[Authorization]` is for the whole class. Split the endpoints into two handlers, or write the special one in `api: endpoints:`. Why, see [Design decisions](#authorization-and-rate-limit-only-for-the-whole-class).

### Does an attribute endpoint send CORS headers?

Only if you set `api: cors:`. Without it the mode is `off` and no CORS headers are sent, the same as for endpoints from neon. See [CORS](/guide/cors).

### How do I see which endpoints were found?

Open the compiled container in `temp/cache/nette.configurator/` and look for `addApi(`. Or list them with the [web console](/guide/console).

### Where is a complete project?

In [examples/attributes](/examples/attributes). Its `e2e.sh` test runs in CI.
