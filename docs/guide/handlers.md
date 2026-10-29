---
title: Handlers
description: Write Nette API handlers - extend BaseHandler, declare params(), outputs(), summary, tags and deprecation, and register them with ApiDecider::addApi().
---

# Handlers

A handler is the code of one endpoint. It implements `Tomaj\NetteApi\Handlers\ApiHandlerInterface`; in practice you extend `BaseHandler` and write `handle()`.

<Diagram name="handlers-1" alt="A handler declares params, outputs and metadata; ApiPresenter, OpenApiHandler and the web console read them" />

## The methods

| Method | Default in BaseHandler | Used by |
| --- | --- | --- |
| `handle(array $params): ResponseInterface` | abstract, you write it | ApiPresenter |
| `params(): array` | `[]` | validation, OpenAPI, console |
| `outputs(): array` | `[]` | output check, OpenAPI |
| `summary(): string` | `''` | OpenAPI, console |
| `description(): string` | `''` | OpenAPI, console |
| `tags(): array` | `[]` | OpenAPI |
| `deprecated(): bool` | `false` | OpenAPI |

`handle()` gets the values of the params keyed by their names. It returns a response such as `JsonApiResponse`, see [Outputs and responses](/guide/outputs).

```php
<?php

declare(strict_types=1);

namespace App\Api;

use App\Model\UserRepository;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\InputType;

final class UserDetailHandler extends BaseHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function summary(): string
    {
        return 'Get one user';
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id', InputType::INTEGER))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $user = $this->users->find($params['id']);
        if ($user === null) {
            return new JsonApiResponse(404, ['status' => 'error', 'message' => 'User not found']);
        }
        return new JsonApiResponse(200, ['id' => $user->id, 'name' => $user->name]);
    }
}
```

`BaseHandler` has a constructor with an optional Fractal `ScopeFactoryInterface`. When your handler has its own constructor, call `parent::__construct()`.

## Registering a handler

`ApiDecider::addApi()` takes the endpoint, the handler, the authorization and an optional rate limit:

```yaml
services:
	- App\Api\UserDetailHandler
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users', 'detail'), App\Api\UserDetailHandler, Tomaj\NetteApi\Authorization\NoAuthorization())
```

The handler can be an object or a class name. A class name is looked up in the DI container only when the endpoint is called, so a handler with heavy dependencies costs nothing on other requests. It also breaks circular dependencies, for example for `OpenApiHandler`, which needs `ApiDecider` itself.

## Knowing your own endpoint

`ApiDecider` calls `setEndpointIdentifier()` before `handle()`, so `$this->getEndpoint()` returns the matched endpoint. The [authorization example](/examples/auth) returns `getEndpoint()->getUrl()`, which is `v1/bearer/` for that route.

`createLink()` builds an absolute link to the same endpoint, after you call `setupLinkGenerator()` with Nette's `LinkGenerator` (a `setup` line in neon, see [Links](/guide/links)); without it, it throws `Nette\InvalidStateException`. For links to other endpoints see [Links and versions](/guide/links).

## Handlers that ship with the library

| Class | What it does |
| --- | --- |
| `DefaultHandler` | Stands for an unknown endpoint; the presenter answers `404 Unknown api endpoint` through the error handler's `handleNotFound()`. `final` since 4.0.0. |
| `AlwaysOkHandler` | Answers `200 {"status":"ok"}`, handy for health checks. |
| `EchoHandler` | Returns the required `status` (`ok` or `error`) and all its params, for testing. |
| `CorsPreflightHandler` | Answers `OPTIONS` requests, see [CORS](/guide/cors). |
| `OpenApiHandler` | The OpenAPI document, see [OpenAPI](/guide/openapi). |
| `ApiListingHandler` | A JSON list of the endpoints in its own version. |

## FAQ

### Do handlers have to be services?

No, `addApi()` accepts an object created in the neon setup too. Register them as services when they need autowired dependencies. The [params example](/examples/params-validation) does that.

### How do I return a different status code?

Pass it as the first argument of the response, for example `new JsonApiResponse(404, [...])`. See [Outputs and responses](/guide/outputs).

### Should I catch exceptions in handle()?

Only when you want a specific answer. Uncaught exceptions go to the error handler and become `500`. See [Error handling](/guide/errors).

### How do I mark an endpoint as deprecated?

Return `true` from `deprecated()`. OpenAPI marks the operation as deprecated; the endpoint keeps working. See [OpenAPI](/guide/openapi).

### Can one handler serve two endpoints?

Yes, register it twice with different `EndpointIdentifier`s. `getEndpoint()` tells you which one was called. See [Links and versions](/guide/links#versions).
