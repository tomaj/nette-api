---
title: 'Example: custom authorization'
description: 'Runnable Nette API example with your own ApiAuthorizationInterface: bearer tokens with roles, the user in the handler, and a token or API key on one endpoint.'
---

# Example: custom authorization

Three endpoints protected by authorization classes written for the app: any user, admins only, and a user or a partner API key.

<ProjectSource name="custom-authorization" :files="['config/common.neon', 'app/RoleTokenAuthorization.php', 'app/AnyOfAuthorization.php', 'app/CurrentUser.php', 'app/Api/MeHandler.php', 'e2e.sh']" />

<Diagram name="examples-custom-authorization-1" alt="The bearer token is looked up, the role is checked, the user is stored in CurrentUser and the handler reads it; an unknown token or a missing role answers 401" />

| Request | Response |
| --- | --- |
| `GET /api/v1/me` without a token | `401 Missing bearer token` |
| `GET /api/v1/me` with `Bearer bob-token` | `200 {"name":"Bob","roles":["staff"]}` |
| `GET /api/v1/report` with `Bearer bob-token` | `401 Role admin is required` |
| `GET /api/v1/report` with `Bearer alice-token` | `200` |
| `GET /api/v1/any/me` with `X-API-Key: partner-key` | `200`, the API key client |

## The services

Each authorization is a separate service with `autowired: false`, so one class serves several endpoints with different settings.

<<< @/../examples/custom-authorization/config/common.neon{yaml}

## The authorization

`authorized()` runs before the handler. It returns `false` with a message for the `401`, or stores the user and returns `true`.

<<< @/../examples/custom-authorization/app/RoleTokenAuthorization.php

## Any of several

<<< @/../examples/custom-authorization/app/AnyOfAuthorization.php

## Passing the user to the handler

`CurrentUser` is a shared service: the authorization writes it, the handler reads it.

<<< @/../examples/custom-authorization/app/CurrentUser.php

<<< @/../examples/custom-authorization/app/Api/MeHandler.php

## The test

<<< @/../examples/custom-authorization/e2e.sh{bash}

## FAQ

### When does authorized() run?

After the rate limit and after the params are read, before param errors and before the handler. See [ApiPresenter pipeline](/guide/presenter).

### Why does the handler not check the role?

The endpoint registration decides who may call it, so the same handler can be public in one place and admins only in another. See [Layers](/guide/layers).

### Why hash_equals() in the loop?

It takes the same time however much of the token is right, so the response time does not help guessing it. See [Authorization](/guide/authorization#tokens-and-ip-restrictions).

### What does the 401 body contain?

`{"status":"error","message":...}` with the text from `getErrorMessage()`. `AnyOfAuthorization` joins the messages of all failed checks.

### How do I show the token in OpenAPI and the console?

`OpenApiHandler` recognises only the built-in classes (`instanceof`). Extend one of them, for example `BearerTokenAuthorization`, or pass your own `securitySchemes` in the handler's `initData`; a custom class gets no per-operation `security` either way. See [OpenAPI](/guide/openapi).
