---
title: 'Príklad: typovaný vstup'
description: 'Spustiteľný príklad Nette API: readonly DTO s query, path, header, JSON alebo formulárovým telom a nahratým súborom.'
---

# Príklad: typovaný vstup

Štyri handlery dedia z `TypedHandler`. DTO na `handleDto()` je kontrakt požiadavky: `#[Query]`, `#[Path]`, `#[Header]`, `#[Body]` a `#[File]`. Rovnaký endpoint objednávky prijme JSON aj formulár. Pozrite [Typovaný vstup](/sk/guide/typed-input).

<ProjectSource name="typed-input" :files="['config/common.neon', 'app/Api/ListUsersHandler.php', 'app/Api/CreateOrderHandler.php', 'app/Api/UserDetailHandler.php', 'app/Api/AvatarUploadHandler.php', 'e2e.sh']" />

::: tip Od verzie 4.0.0
Typované handlery, path a header parametre a `#[Consumes]` sú nové v 4.0.0.
:::

| Požiadavka | Stav | Čo ukazuje |
| --- | --- | --- |
| `GET /api/v1/users/list` | `200` | `page` má predvolenú hodnotu `1`, `status` je null |
| `GET /api/v1/users/list?page=2&status=active` | `200` | celé číslo a backed enum |
| `GET /api/v1/users/list?page=1a` | `400` | `1a` nie je celé číslo |
| `GET /api/v1/users/42/detail` s `X-Request-Id` | `200` | id z cesty a hlavička |
| `POST /api/v1/orders` JSON | `201` | vnorené DTO a `list<ItemInput>` |
| `POST /api/v1/orders` formulár | `201` | to isté DTO, zátvorkové mená |
| `POST /api/v1/orders` neplatné telo | `400` | `items[1].quantity` |
| `POST /api/v1/orders` `text/csv` | `415` | content type mimo predvolenej dvojice |
| `POST /api/v1/orders` PSČ `81101` a nový riadok | `400` | `$` vo vzore končí reťazec |
| `POST /api/v1/avatar` multipart | `201` | `FileUpload` |
| `GET /api/v1/openapi` | `200` | path parameter a oba content typy tela |

## Query DTO

<<< @/../examples/typed-input/app/Api/Input/ListUsersInput.php

<<< @/../examples/typed-input/app/Api/ListUsersHandler.php

## DTO s cestou a hlavičkou

<<< @/../examples/typed-input/app/Api/Input/UserDetailInput.php

<<< @/../examples/typed-input/app/Api/UserDetailHandler.php

## Nahrávanie súboru

<<< @/../examples/typed-input/app/Api/Input/AvatarInput.php

<<< @/../examples/typed-input/app/Api/AvatarUploadHandler.php

## Registrácia

Endpoint s cestou je `#[Get(1, 'users', '{id}/detail')]`. Routa je obvyklá, s voliteľným tretím segmentom:

<<< @/../examples/typed-input/app/RouterFactory.php

<<< @/../examples/typed-input/config/common.neon
