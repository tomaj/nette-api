---
title: 'Príklad: obmedzenie požiadaviek'
description: 'Spustiteľný príklad Nette API s vlastným RateLimitInterface: tri požiadavky za hodinu na API kľúč, hlavičky X-RateLimit, Retry-After a vlastné telo 429.'
---

# Príklad: obmedzenie požiadaviek

`GET /api/v1/quote` povolí tri požiadavky za hodinu na API kľúč. Štvrtá odpovie `429` s `Retry-After`.

<ProjectSource name="rate-limit" :files="['config/common.neon', 'app/FileRateLimit.php', 'app/Api/QuoteHandler.php', 'e2e.sh']" />

<Diagram name="examples-rate-limit-1" alt="FileRateLimit počíta požiadavky na API kľúč v súbore; kým požiadavky zostávajú, presenter pridá hlavičky X-RateLimit a pokračuje k autorizácii; pri nule odpovie 429 s Retry-After" />

| Požiadavka | Odpoveď |
| --- | --- |
| 1. až 3. s `X-Api-Key: key-a` | `200`, `X-RateLimit-Remaining` 3, 2, 1 |
| 4. s `key-a` | `429 Slow down`, `X-RateLimit-Remaining: 0` a `Retry-After` |
| 1. s `key-b` | `200`, vlastné počítadlo, `Remaining: 3` |
| zlý kľúč | hlavičky obmedzenia požiadaviek, potom `401` |

## Obmedzenie požiadaviek

`check()` vráti `RateLimitResponse(limit, remaining, retryAfter, errorResponse)`. Presenter odpovie `429`, keď je `remaining` `0`.

<<< @/../examples/rate-limit/app/FileRateLimit.php

## Služby

Obmedzenie požiadaviek sa nastavuje štvrtým argumentom `addApi()`.

<<< @/../examples/rate-limit/config/common.neon{yaml}

## Handler

<<< @/../examples/rate-limit/app/Api/QuoteHandler.php

## Test

<<< @/../examples/rate-limit/e2e.sh{bash}

## Časté otázky

### Prečo sa počíta aj zlý kľúč?

Obmedzenie požiadaviek beží pred autorizáciou, takže obmedzený je aj klient, ktorý kľúče hádá. Pozrite si [Spracovanie v ApiPresenteri](/sk/guide/presenter).

### Prečo súbor a flock()?

Aby príklad nemal závislosti. V produkcii použite Redis s `INCR` a `EXPIRE` alebo databázu.

### Čo ak nevrátim chybovú odpoveď?

Presenter pošle `429` s `Too many requests. Retry after N seconds.`. Pozrite si [Obmedzenie požiadaviek](/sk/guide/rate-limit).

### Ktoré hlavičky klient dostane?

`X-RateLimit-Limit`, `X-RateLimit-Remaining` a pri limite `Retry-After`.

### Môžem obmedzovať podľa IP?

Áno, príklad použije IP, keď kľúč chýba. Použite detektor IP, ktorý sa nedá podvrhnúť, pozrite si [Autorizácia](/sk/guide/authorization#tokeny-a-obmedzenie-ip).
