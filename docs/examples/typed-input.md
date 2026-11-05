---
title: 'Example: typed input'
description: 'Runnable Nette API example: a readonly DTO with query, path, header, JSON or form body and a file upload.'
---

# Example: typed input

Four handlers extend `TypedHandler`. The DTO on `handleDto()` is the request contract: `#[Query]`, `#[Path]`, `#[Header]`, `#[Body]` and `#[File]`. The same order endpoint accepts JSON and a URL-encoded form. See [Typed input](/guide/typed-input).

<ProjectSource name="typed-input" :files="['config/common.neon', 'app/Api/ListUsersHandler.php', 'app/Api/CreateOrderHandler.php', 'e2e.sh']" />

::: tip Since 4.0.0
Typed handlers, path and header parameters and `#[Consumes]` are new in 4.0.0.
:::

| Request | Status | What it shows |
| --- | --- | --- |
| `GET /api/v1/users/list` | `200` | `page` defaults to `1`, `status` is null |
| `GET /api/v1/users/list?page=2&status=active` | `200` | integer and backed enum |
| `GET /api/v1/users/list?page=1a` | `400` | `1a` is not an integer |
| `GET /api/v1/users/42/detail` with `X-Request-Id` | `200` | path id and header |
| `POST /api/v1/orders` JSON | `201` | nested DTO and `list<ItemInput>` |
| `POST /api/v1/orders` form | `201` | the same DTO, bracket names |
| `POST /api/v1/orders` invalid body | `400` | `items[1].quantity` |
| `POST /api/v1/orders` `text/csv` | `415` | content type outside the default pair |
| `POST /api/v1/avatar` multipart | `201` | `FileUpload` |
| `GET /api/v1/openapi` | `200` | path parameter and both body content types |

## A query DTO

<<< @/../examples/typed-input/app/Api/Input/ListUsersInput.php

<<< @/../examples/typed-input/app/Api/ListUsersHandler.php

## Registration

The path endpoint is `#[Get(1, 'users', '{id}/detail')]`. The route is the usual one, with an optional third segment:

<<< @/../examples/typed-input/app/RouterFactory.php

<<< @/../examples/typed-input/config/common.neon
