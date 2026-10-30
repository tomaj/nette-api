---
title: 'Príklad: logovanie a chyby'
description: 'Spustiteľný príklad Nette API: log požiadaviek do JSON riadkov s maskovanými hlavičkami a query parametrami, vlastný error handler pre 503, logy v Tracy a chyby v produkcii.'
---

# Príklad: logovanie a chyby

Tri endpointy, ktoré ukazujú, čo sa loguje a čo vidí klient, pri vývoji aj v produkcii.

<ProjectSource name="logging-errors" :files="['config/common.neon', 'app/FileApiLogger.php', 'app/AppErrorHandler.php', 'app/Api/FailHandler.php', 'e2e.sh']" />

<Diagram name="examples-logging-errors-1" alt="ping vráti 200, fail vráti 500, 503 alebo 400, broken vráti 500 pri vývoji a 200 v produkcii; spracované požiadavky idú do api.log, výnimky do Tracy" />

::: tip Od verzie 4.0.0
Presenter predvolene maskuje `Authorization`, `Cookie`, `X-Api-Key`, hlavičky ako `X-Refresh-Token` a query parametre ako `api_key`, takže ich logger nikdy nevidí. Dva riadky v `setup` pridajú `X-Session` a `session_id`. V 3.6.0 sa maskovali len hlavičky, po `setMaskedLogHeaders()`. `400`, `401` a `429` sa nelogujú. Pozrite si [Logovanie](/sk/guide/logging#maskovanie).
:::

| Požiadavka | Vývoj | Produkcia |
| --- | --- | --- |
| `/api/v1/ping` | `200`, zalogované | `200`, zalogované |
| `/api/v1/fail?kind=crash` | `500` s `detail`, v Tracy logu | `500` bez `detail` |
| `/api/v1/fail?kind=maintenance` | `503` z `AppErrorHandler` | `503` |
| `/api/v1/fail` | `400` s `detail`, nezalogované | `400` bez `detail` |
| `/api/v1/broken` | `500`, výstup nezodpovedá | `200`, nekontroluje sa |

## Služby

<<< @/../examples/logging-errors/config/common.neon{yaml}

## Logger

<<< @/../examples/logging-errors/app/FileApiLogger.php

## Error handler

<<< @/../examples/logging-errors/app/AppErrorHandler.php

## Zlyhávajúci handler

<<< @/../examples/logging-errors/app/Api/FailHandler.php

## Test

<<< @/../examples/logging-errors/e2e.sh{bash}

## Časté otázky

### Ako sa zapne produkcia?

`EnvConfigurator('APP_ENV', 'production')` vypne detaily aj kontrolu výstupov, keď `APP_ENV=production`. Pozrite si [Výstupy](/sk/guide/outputs#kedy-sa-vystupy-validuju).

### Kde sa hlavičky maskujú?

V presenteri, predvolene; `common.neon` len pridá `X-Session` a `session_id`. Test pošle token, cookie, `X-Session`, `X-Refresh-Token`, `?api_key=` a `?session_id=` a overí, že `api.log` má pri každom `***`, žiadnu tajnú hodnotu, a ponechá `X-Trace` a `page=2`. Pozrite si [Logovanie](/sk/guide/logging#maskovanie).

### Prečo 400 nie je v api.log?

Zlý vstup v 3.x skončí pred loggerom. Pozrite si [Logovanie](/sk/guide/logging#co-sa-loguje).

### Kde sú výnimky?

V `log/exception.log` a HTML súboroch Tracy, ktoré zapisuje `DefaultErrorHandler`.

### Prečo DefaultErrorHandler obaliť a nie zdediť?

Je `final`. Pozrite si [Spracovanie chýb](/sk/guide/errors#vlastny-error-handler).
