---
title: 'Príklad: endpointy z atribútov'
description: 'Spustiteľný príklad Nette API: endpointy zapísané atribútmi #[Get], #[Post], #[Authorization] a #[RateLimit] na triedach handlerov, nájdené cez discovery v NetteApiExtension.'
---

# Príklad: endpointy z atribútov

API používateľov z [príkladu DI rozšírenia](/sk/examples/di-extension), ale endpointy sú zapísané priamo na triedach handlerov [atribútmi](/sk/guide/attributes). Sekcia `api:` nemá zoznam `endpoints:`, iba `discovery:`.

<ProjectSource name="attributes" :files="['config/common.neon', 'app/Api/HelloHandler.php', 'app/Api/UserCreateHandler.php', 'e2e.sh']" />

::: tip Od verzie 4.0.0
Atribúty a `api: discovery:` sú nové vo verzii 4.0.0.
:::

| Požiadavka | Status | Atribút |
| --- | --- | --- |
| `GET /api/v1/hello` | `200` | `#[Get(1, 'hello')]`, `#[Authorization(false)]` |
| `GET /api/v1/users` bez tokenu | `401` | `#[Get(1, 'users')]`, predvolená autorizácia |
| `GET /api/v1/users` s `Authorization: Bearer secret-token` | `200` | `#[Get(1, 'users')]` |
| `GET /api/v1/users/detail?id=2` | `200` | `#[Get(1, 'users', 'detail')]` |
| `GET /api/v1/users/detail` | `400` | chýba `id`, problem detail |
| `POST /api/v1/users` `name=Carol` | `201`, `201`, potom `429` | `#[Post(1, 'users')]`, `#[RateLimit('strict')]` |
| `OPTIONS /api/v1/users` | `200` | CORS preflight, token netreba |
| `GET /api/v1/nothing` | `404` | žiadny atribút |

## Konfigurácia

<<< @/../examples/attributes/config/common.neon{yaml}

- `discovery: paths` a `namespace` povedia rozšíreniu, aby prečítalo triedy v `app/Api`.
- `HelloHandler('Hello from attributes')` je registrovaný v `services:`, lebo potrebuje reťazec. Jeho atribúty sa prečítajú zo služby, druhý handler sa nevytvorí.
- `authorizations: token` a `rateLimits: strict` sú mená, ktoré používajú atribúty.

## Verejný endpoint

<<< @/../examples/attributes/app/Api/HelloHandler.php

## Endpoint s obmedzením požiadaviek

<<< @/../examples/attributes/app/Api/UserCreateHandler.php

## Handler so závislosťou

<<< @/../examples/attributes/app/Api/UsersListHandler.php

## Endpoint s akciou

<<< @/../examples/attributes/app/Api/UserDetailHandler.php

## Test

<<< @/../examples/attributes/e2e.sh{bash}

## Časté otázky

### Ako ho spustím?

`composer install` a `./e2e.sh` v `examples/attributes`. Test spustí vstavaný PHP server na porte 8098.

### Ako pridám endpoint?

Napíšte handler do `app/Api/` s `#[Get(1, 'something')]`. Neon konfigurácia sa nemení; v debug režime sa kontajner prebuduje sám.

### Čím sa líši od príkladu DI rozšírenia?

Iba tým, kde sú endpointy zapísané. Handlery, formát chýb, CORS a obmedzenie požiadaviek sú rovnaké, rovnako aj skompilovaný kontajner.

### Čo sa stane pri preklepe v atribúte?

Kontajner sa neskompiluje. Skúste `#[Authorization('tokne')]`: správa uvedie endpoint aj triedu s atribútom.

### Dá sa chrániť iba časť endpointov jedného handlera?

Nie, `#[Authorization]` platí pre celú triedu. Napíšte dva handlery, ako tu `UsersListHandler` a `UserCreateHandler`.
