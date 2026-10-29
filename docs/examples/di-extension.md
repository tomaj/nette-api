---
title: 'Example: DI extension'
description: 'Runnable Nette API example: the whole API in one api: neon section with NetteApiExtension, a default bearer token, a public endpoint, a rate limit, CORS preflight and Problem Details.'
---

# Example: DI extension

A users API configured only in the `api:` section of `config/common.neon`, with the [DI extension](/guide/di-extension). There is no `ApiDecider`, `ApiPresenter` or error handler in `services:`.

<ProjectSource name="di-extension" :files="['config/common.neon', 'app/Api/UsersListHandler.php', 'app/FileRateLimit.php', 'e2e.sh']" />

::: tip Since 4.0.0
`NetteApiExtension` is new in 4.0.0.
:::

| Request | Status | Note |
| --- | --- | --- |
| `GET /api/v1/hello` | `200` | public, `authorization: false` |
| `GET /api/v1/users` without a token | `401` | the default authorization, as a problem detail |
| `GET /api/v1/users` with `Authorization: Bearer secret-token` | `200` | the list from `UserRepository` |
| `GET /api/v1/users/detail?id=2` | `200` | `action: detail` |
| `GET /api/v1/users/detail` | `400` | `type` `https://api.example.com/problems/validation-error` |
| `POST /api/v1/users` `name=Carol` | `201`, `201`, then `429` | `rateLimit: strict`, 2 requests per minute |
| `OPTIONS /api/v1/users` | `200` | preflight, no token needed |
| `GET /api/v1/nothing` | `404` | `type` `https://api.example.com/problems/not-found` |

## The configuration

<<< @/../examples/di-extension/config/common.neon{yaml}

- `defaults: authorization: token` protects every endpoint, `authorization: false` makes `hello` public.
- `HelloHandler('Hello from neon')` is a statement, the handler gets its argument from the config.
- `UsersListHandler` gets `UserRepository` from its constructor by autowiring.

## A handler with a dependency

<<< @/../examples/di-extension/app/Api/UsersListHandler.php

## The rate limit

<<< @/../examples/di-extension/app/FileRateLimit.php

## The bootstrap

<<< @/../examples/di-extension/app/Bootstrap.php

## The test

<<< @/../examples/di-extension/e2e.sh{bash}

## FAQ

### How do I run it?

`composer install` and `./e2e.sh` in `examples/di-extension`. The test starts the PHP built-in server on port 8097.

### Where is the ApiPresenter registered?

The extension registers it as `api.presenter`. The `application: mapping` and the route in `RouterFactory` are still in the application.

### How do I add an endpoint?

Write the handler in `app/Api/` and add one line to `api: endpoints:`. Nothing else changes.

### What happens with a typo in the config?

The container does not compile, and the message names the endpoint and the key. Try `authorization: tokne`.

### Can I use it with the services from the other examples?

Yes, an `ApiDecider` or error handler in `services:` is used instead of the extension's one, see [Moving from services](/guide/di-extension#moving-from-services).
