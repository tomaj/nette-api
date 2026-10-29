---
title: 'Example: attribute endpoints'
description: 'Runnable Nette API example: endpoints declared with #[Get], #[Post], #[Authorization] and #[RateLimit] on the handler classes, found by NetteApiExtension discovery.'
---

# Example: attribute endpoints

The users API from the [DI extension example](/examples/di-extension), but the endpoints are written on the handler classes with [attributes](/guide/attributes). The `api:` section has no `endpoints:` list, only `discovery:`.

<ProjectSource name="attributes" :files="['config/common.neon', 'app/Api/HelloHandler.php', 'app/Api/UserCreateHandler.php', 'e2e.sh']" />

::: tip Since 4.0.0
The attributes and `api: discovery:` are new in 4.0.0.
:::

| Request | Status | Attribute |
| --- | --- | --- |
| `GET /api/v1/hello` | `200` | `#[Get(1, 'hello')]`, `#[Authorization(false)]` |
| `GET /api/v1/users` without a token | `401` | `#[Get(1, 'users')]`, the default authorization |
| `GET /api/v1/users` with `Authorization: Bearer secret-token` | `200` | `#[Get(1, 'users')]` |
| `GET /api/v1/users/detail?id=2` | `200` | `#[Get(1, 'users', 'detail')]` |
| `GET /api/v1/users/detail` | `400` | missing `id`, a problem detail |
| `POST /api/v1/users` `name=Carol` | `201`, `201`, then `429` | `#[Post(1, 'users')]`, `#[RateLimit('strict')]` |
| `OPTIONS /api/v1/users` | `200` | CORS preflight, no token needed |
| `GET /api/v1/nothing` | `404` | no attribute for it |

## The configuration

<<< @/../examples/attributes/config/common.neon{yaml}

- `discovery: paths` and `namespace` tell the extension to read the classes in `app/Api`.
- `HelloHandler('Hello from attributes')` is registered in `services:` because it needs a string. Its attributes are read from the service, no second handler is created.
- `authorizations: token` and `rateLimits: strict` are the names the attributes use.

## A public endpoint

<<< @/../examples/attributes/app/Api/HelloHandler.php

## A rate-limited endpoint

<<< @/../examples/attributes/app/Api/UserCreateHandler.php

## A handler with a dependency

<<< @/../examples/attributes/app/Api/UsersListHandler.php

## An endpoint with an action

<<< @/../examples/attributes/app/Api/UserDetailHandler.php

## The test

<<< @/../examples/attributes/e2e.sh{bash}

## FAQ

### How do I run it?

`composer install` and `./e2e.sh` in `examples/attributes`. The test starts the PHP built-in server on port 8098.

### How do I add an endpoint?

Write a handler in `app/Api/` with `#[Get(1, 'something')]`. The neon config does not change; in debug mode the container rebuilds on its own.

### How does it differ from the DI extension example?

Only in where the endpoints are written. The handlers, the error format, CORS and the rate limit are the same, and so is the compiled container.

### What happens with a typo in an attribute?

The container does not compile. Try `#[Authorization('tokne')]`: the message names the endpoint and the class with the attribute.

### Can I protect only some endpoints of one handler?

No, `#[Authorization]` is for the whole class. Write two handlers, like `UsersListHandler` and `UserCreateHandler` here.
