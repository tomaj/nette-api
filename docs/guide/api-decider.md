---
title: ApiDecider and endpoints
description: How Nette API ApiDecider registers endpoints with addApi(), matches method, version, package and action, creates handlers lazily and answers unknown URLs with 404.
---

# ApiDecider and endpoints

`Tomaj\NetteApi\ApiDecider` is the list of endpoints. `ApiPresenter` asks it once per request which handler, authorization and rate limit to use.

<Diagram name="api-decider-1" alt="The route parameters and the HTTP method go to getApi; ApiDecider compares them with every registered EndpointIdentifier; the first match returns the handler, authorization and rate limit; no match returns DefaultHandler with 404" />

## Registering endpoints

```php
public function addApi(
    EndpointInterface $endpointIdentifier,
    ApiHandlerInterface|string $handler,
    ApiAuthorizationInterface $apiAuthorization,
    ?RateLimitInterface $rateLimit = null
): self
```

In NEON:

```yaml
services:
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), App\Api\UserListHandler, Tomaj\NetteApi\Authorization\NoAuthorization())
			- addApi(Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users'), '@userCreateHandler', @bearer, @writeLimit)
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 2, 'users', 'detail'), App\Api\UserDetailV2Handler(), @bearer)
```

## EndpointIdentifier

| Argument | Example | Note |
| --- | --- | --- |
| method | `'GET'` | compared upper case |
| version | `1`, `'1.1'` | stored as a string, must not contain `/` |
| package | `'users'` | the first path segment after the version |
| apiAction | `'detail'` or `null` | optional second segment |

`getUrl()` returns `v1/users/detail`, which [OpenAPI](/guide/openapi) and the console use.

## How a request is matched

`getApi($method, $version, $package, $apiAction)`:

1. upper-cases the method and turns an empty `apiAction` into `null`;
2. walks the endpoints **in the order they were added**;
3. returns the first one where method, version, package and action are all equal;
4. sets the endpoint on the handler, so `getEndpoint()` and `createLink()` work;
5. with no match returns `DefaultHandler` and `NoAuthorization`, which answer `404 Unknown api endpoint` through the error handler's `handleNotFound()`.

`GET /api/v1/users` and `POST /api/v1/users` are two endpoints with two handlers. A version that is not registered, such as `/api/v3/users`, is a `404`, as the [versions example](/examples/versions) tests.

## Three ways to pass a handler

| Value | Created |
| --- | --- |
| `App\Api\UserListHandler` (class name) | lazily, `getByType()` from the container when the endpoint is called |
| `'@userCreateHandler'` (service name) | lazily, `getByName()` |
| `App\Api\UserDetailV2Handler()` (object) | when the decider is created |

A class name or a service name keeps the start of every request fast, because only the called handler and its dependencies are created. More in [Handlers](/guide/handlers#registering-a-handler).

The quotes matter. `'@userCreateHandler'` in quotes is a plain string, the service name, which `ApiDecider` resolves only when the endpoint is called. Without quotes, as with `@bearer` and `@writeLimit` above, Nette injects the service itself, so it is created together with the decider. That is fine for small authorization and rate-limit objects; for handlers with heavy dependencies use the quoted name or the class name.

## Preflight for all endpoints

`enableGlobalPreflight()` answers `OPTIONS` for every registered URL with `CorsPreflightHandler`, without an `addApi()` per endpoint. See [CORS](/guide/cors#preflight-requests).

## Listing endpoints

`getApis()` returns every endpoint with its handler, authorization and rate limit. `OpenApiHandler` and `ApiListingControl` build on it. Note that it creates every handler.

## FAQ

### What happens with two identical endpoints?

The first one added wins; the second is never called.

### Is the version compared as a number?

No, as a string. `1` and `'1'` match, `'1.0'` does not match `1`.

### Can the package contain a slash?

No, the route takes one segment. Use `apiAction` for a second level.

### Can I use a different URL structure?

Yes, change the route, as long as it fills `version`, `package` and optionally `apiAction`.

### Why is my handler created on every request?

It was passed as an object, `App\Api\Handler()`, or `getApis()` was called. Pass the class name instead.
