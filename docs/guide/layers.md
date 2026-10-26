---
title: Layers
description: What each layer of Nette API does, from the route and ApiPresenter to authorization, params, handler, outputs, errors and logger, and which interface changes it.
---

# Layers

A request passes through a fixed set of layers. Each one has a single job and its own interface, so you replace one without touching the others. The handler, where your logic lives, sees only clean values.

One request that passes, one without a token and one with wrong input:

<LayersFlow />

<Diagram name="layers-1" alt="Route, ApiPresenter, ApiDecider, rate limit, authorization, params, handler, outputs, error handler and logger, each with its interface" />

| Layer | Interface or class | Its job | Stops the request with |
| --- | --- | --- | --- |
| Route | Nette router | Turns `/api/v1/users` into `version`, `package`, `apiAction` | |
| Presenter | `ApiPresenter` | Runs the layers below in order | |
| CORS | `setCorsHeader()`, `setCorsAllowedOrigins()` | Adds `Access-Control-*` headers | |
| Endpoint lookup | `ApiDecider`, `EndpointIdentifier` | Finds the handler, authorization and rate limit | `404` |
| Rate limit | `RateLimitInterface` | Counts calls per client | `429` |
| Params | `InputParam`, `ParamsProcessor` | Reads and validates input; errors wait for authorization | |
| Authorization | `ApiAuthorizationInterface` | Decides who may call | `401` |
| Param errors | `ErrorHandlerInterface::handleInputParams()` | Reports invalid input | `400` |
| Handler | `ApiHandlerInterface`, `BaseHandler` | Your logic, returns a response | any code |
| Outputs | `OutputInterface`, `ConfiguratorInterface` | Checks the response against its schema | `500` |
| Errors | `ErrorHandlerInterface` | Turns failures into JSON | |
| Logger | `ApiLoggerInterface` | Records handled requests | |

The exact order, with code, is on [ApiPresenter pipeline](/guide/presenter).

## Route

One route sends every API URL to the presenter. The placeholders become the parts of the endpoint.

```php
$router->addRoute('/api/v<version>/<package>[/<apiAction>][/<params>]', 'Api:Api:default');
```

`GET /api/v1/users/detail` gives `version` `1`, `package` `users` and `apiAction` `detail`. See [Getting started](/guide/getting-started).

## ApiDecider: which endpoint

`ApiDecider` holds the list of endpoints. Each entry connects an `EndpointIdentifier` with a handler, an authorization and an optional rate limit:

```yaml
- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'detail'), App\Api\UserDetailHandler, @bearer, @userLimit)
```

So **who may call** and **how often** belong to the endpoint, not to the handler. One handler can be public under one URL and protected under another. See [ApiDecider and endpoints](/guide/api-decider).

## Rate limit: how often

`RateLimitInterface::check()` returns `null` for no limit, or a `RateLimitResponse` with the limit and what remains. At `0` the presenter answers `429`. It runs before authorization, so a client guessing tokens is limited too. See [Rate limiting](/guide/rate-limit) and the [rate limit example](/examples/rate-limit).

## Authorization: who

`ApiAuthorizationInterface` has two methods:

```php
public function authorized(): bool;
public function getErrorMessage(): ?string;
```

`false` answers `401` with the message. Built in are `NoAuthorization`, `BearerTokenAuthorization`, header, query and cookie API keys, and `BasicAuthentication`. Write your own for roles or sessions, as in the [custom authorization example](/examples/custom-authorization). See [Authorization](/guide/authorization).

## Params: what came in

`params()` of the handler lists the inputs. Each param knows where to read (query, POST, PUT, cookie, file, raw or JSON body), whether it is required, its type and allowed values:

```php
public function params(): array
{
    return [
        (new GetInputParam('page', InputType::INTEGER))->setDefault(1),
        (new JsonInputParam('order', file_get_contents(__DIR__ . '/schema/order.json')))->setRequired(),
    ];
}
```

The presenter reads them all, then reports errors as `400` with a message per param. The handler never sees invalid input. See [Input params](/guide/params).

## Handler: your logic

The handler gets `$params` with the clean values and returns a response. It does not check tokens or parse the request.

```php
public function handle(array $params): ResponseInterface
{
    $user = $this->users->find($params['id']);
    if ($user === null) {
        return new JsonApiResponse(404, ['status' => 'error', 'message' => 'User not found']);
    }
    return new JsonApiResponse(200, ['id' => $user->id, 'name' => $user->name]);
}
```

`summary()`, `description()`, `tags()` and `deprecated()` describe the endpoint for [OpenAPI](/guide/openapi). See [Handlers](/guide/handlers).

## Outputs: what goes out

`outputs()` lists the allowed responses, each a code and a JSON schema. When validation is on, the response must match at least one, otherwise the client gets `500`. A handler with no outputs is not checked. The configurator decides when to check, for example `EnvConfigurator` only outside production. See [Outputs](/guide/outputs).

## Error handler: when something fails

`ErrorHandlerInterface` builds the response for each failure:

| Method | When | Default answer |
| --- | --- | --- |
| `handle()` | the handler threw | `500`, with `detail` outside production |
| `handleInputParams()` | wrong params | `400 wrong input` |
| `handleSchema()` | the output did not match | `500` |
| `handleAuthorization()` | `authorized()` returned false | `401` with the message |
| `handleAuthorizationException()` | `authorized()` threw | `500` with the exception message |

See [Error handling](/guide/errors).

## Logger: what happened

With an `ApiLoggerInterface` service in the container, every request that reached the handler is logged with code, method, headers, URI, IP, user agent and time. `setMaskedLogHeaders()` hides secret headers. See [Logging](/guide/logging).

## FAQ

### Where do I put business logic?

In the handler, or in a service it calls. The other layers only decide whether it runs and check what it returns.

### Can the handler read the authorized user?

Yes, through a shared service the authorization fills, as `CurrentUser` in the [custom authorization example](/examples/custom-authorization).

### Why are params read before authorization but reported after it?

So a caller without access gets `401` and learns nothing about the params. `authorized()` takes no arguments and never sees the parsed values; an authorization that needs one reads the HTTP request itself, as `QueryApiKeyAuthentication` reads `$_GET`. The values go only to the error handler, and are `[]` when they are invalid.

### Which layers can I leave out?

Rate limit, logger and outputs are optional. Authorization is required per endpoint; use `NoAuthorization` for public ones.

### How do I test one layer?

Each is a plain object: call `handle()`, `authorized()`, `ParamsProcessor` or `validate()` directly. See the [testing example](/examples/testing).
