---
title: 'Príklad: Problem Details'
description: 'Spustiteľný príklad Nette API: RFC 9457 application/problem+json pre 400, 401, 404, 409, 429 a 500, produkčný režim, výber podľa Accept a schéma ProblemDetails v OpenAPI.'
---

# Príklad: Problem Details

Všetky chyby API objednávok ako [RFC 9457 problem details](/sk/guide/problem-details), pri vývoji, v produkcii a len pre klientov, ktorí o ne žiadajú.

<ProjectSource name="problem-details" :files="['config/common.neon', 'config/negotiate.neon', 'app/Api/OrderCreateHandler.php', 'app/ExhaustedRateLimit.php', 'e2e.sh']" />

::: tip Od verzie 4.0.0
`ProblemDetailsErrorHandler`, `NegotiatingErrorHandler` a `ProblemDetailsResponse` sú nové vo verzii 4.0.0.
:::

| Požiadavka | Stav | `type` | Poznámka |
| --- | --- | --- | --- |
| `POST /api/v1/orders` `product=book&quantity=2` | `201` | | `application/json` |
| `POST /api/v1/orders` `quantity=9` | `400` | `validation-error` | `errors` pre `product` a `quantity`, aj v produkcii |
| `POST /api/v1/orders` `product=sold-out&quantity=2` | `409` | `out-of-stock` | vráti ho handler, s `product` a `available` |
| `POST /api/v1/orders` `product=crash&quantity=1` | `500` | `internal-error` | text len pri vývoji, vždy v `log/exception.log` |
| `GET /api/v1/me` bez tokenu | `401` | `unauthorized` | |
| `GET /api/v1/limited` | `429` | `rate-limited` | `limit`, `retryAfter` a `Retry-After: 30` |
| `GET /api/v1/nothing` | `404` | `not-found` | |
| `GET /api/v1/openapi` | `200` | | schéma `ProblemDetails` pre chyby |

S `ERRORS=negotiate` majú chyby formát 3.x, kým klient nepošle `Accept: application/problem+json`.

## Služby

<<< @/../examples/problem-details/config/common.neon{yaml}

## Výber podľa Accept

<<< @/../examples/problem-details/config/negotiate.neon{yaml}

<<< @/../examples/problem-details/app/Bootstrap.php

## Handler

<<< @/../examples/problem-details/app/Api/OrderCreateHandler.php

## Obmedzenie požiadaviek

<<< @/../examples/problem-details/app/ExhaustedRateLimit.php

## Test

<<< @/../examples/problem-details/e2e.sh{bash}

## Časté otázky

### Ako ho spustím?

`composer install` a `./e2e.sh` v `examples/problem-details`. Test spustí vstavaný PHP server na portoch 8091, 8092 a 8093.

### Prečo 409 potrebuje výstup?

Pri vývoji sa výstupy validujú a odpoveď s kódom, ktorý nie je v `outputs()`, sa zmení na `500` z `handleSchema()`. Handler uvádza `409` so schémou `ResponseBuilder::problemDetailsSchema()`.

### Prečo je type URL, ktorá neexistuje?

`api.example.com` je len zástupná adresa. Nasmerujte `problemTypeBaseUri` na stránky, ktoré vaše problémy opisujú.

### Ako ponechám starý formát pre starých klientov?

Použite `config/negotiate.neon`: `NegotiatingErrorHandler` pošle problem details len klientom s `Accept: application/problem+json`.
