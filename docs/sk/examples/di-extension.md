---
title: 'Príklad: DI rozšírenie'
description: 'Spustiteľný príklad Nette API: celé API v jednej neon sekcii api: cez NetteApiExtension, predvolený bearer token, verejný endpoint, obmedzenie požiadaviek, CORS preflight a Problem Details.'
---

# Príklad: DI rozšírenie

API používateľov nastavené iba v sekcii `api:` súboru `config/common.neon`, cez [DI rozšírenie](/sk/guide/di-extension). V `services:` nie je `ApiDecider`, `ApiPresenter` ani error handler.

<ProjectSource name="di-extension" :files="['config/common.neon', 'app/Api/UsersListHandler.php', 'app/FileRateLimit.php', 'e2e.sh']" />

::: tip Od verzie 4.0.0
`NetteApiExtension` je novinka vo verzii 4.0.0.
:::

| Požiadavka | Stav | Poznámka |
| --- | --- | --- |
| `GET /api/v1/hello` | `200` | verejný, `authorization: false` |
| `GET /api/v1/users` bez tokenu | `401` | predvolená autorizácia, ako problem detail |
| `GET /api/v1/users` s `Authorization: Bearer secret-token` | `200` | zoznam z `UserRepository` |
| `GET /api/v1/users/detail?id=2` | `200` | `action: detail` |
| `GET /api/v1/users/detail` | `400` | `type` `https://api.example.com/problems/validation-error` |
| `POST /api/v1/users` `name=Carol` | `201`, `201`, potom `429` | `rateLimit: strict`, 2 požiadavky za minútu |
| `OPTIONS /api/v1/users` | `200` | preflight, token netreba |
| `GET /api/v1/nothing` | `404` | `type` `https://api.example.com/problems/not-found` |

## Konfigurácia

<<< @/../examples/di-extension/config/common.neon{yaml}

- `defaults: authorization: token` chráni každý endpoint, `authorization: false` robí `hello` verejným.
- `HelloHandler('Hello from neon')` je statement, handler dostane argument z konfigurácie.
- `UsersListHandler` dostane `UserRepository` v konštruktore autowiringom.

## Handler so závislosťou

<<< @/../examples/di-extension/app/Api/UsersListHandler.php

## Obmedzenie požiadaviek

<<< @/../examples/di-extension/app/FileRateLimit.php

## Bootstrap

<<< @/../examples/di-extension/app/Bootstrap.php

## Test

<<< @/../examples/di-extension/e2e.sh{bash}

## Časté otázky

### Ako ho spustím?

`composer install` a `./e2e.sh` v `examples/di-extension`. Test spustí vstavaný PHP server na porte 8097.

### Kde je zaregistrovaný ApiPresenter?

Rozšírenie ho zaregistruje ako `api.presenter`. `application: mapping` a routa v `RouterFactory` zostávajú v aplikácii.

### Ako pridám endpoint?

Napíšte handler do `app/Api/` a pridajte jeden riadok do `api: endpoints:`. Nič iné sa nemení.

### Čo sa stane pri preklepe v konfigurácii?

Kontajner sa neskompiluje a správa pomenuje endpoint aj kľúč. Skúste `authorization: tokne`.

### Dá sa použiť so službami z iných príkladov?

Áno, `ApiDecider` alebo error handler v `services:` sa použije namiesto toho z rozšírenia, pozrite [Prechod zo services](/sk/guide/di-extension#prechod-zo-services).
