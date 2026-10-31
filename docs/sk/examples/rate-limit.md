---
title: 'Príklad: obmedzenie požiadaviek'
description: 'Spustiteľný príklad Nette API so vstavanými obmedzeniami: predvolený limit na IP pre celú aplikáciu, kvóta na kľúč po autorizácii, vlastný #[RateLimit] na handleri a endpoint bez limitu.'
---

# Príklad: obmedzenie požiadaviek

Každý endpoint povolí 100 požiadaviek za minútu z jednej IP. `GET /api/v1/quote` navyše povolí tri požiadavky za hodinu na API kľúč, `GET /api/v1/ping` dve za minútu podľa svojho atribútu a `GET /api/v1/status` nemá limit.

<ProjectSource name="rate-limit" :files="['config/common.neon', 'app/Api/QuoteHandler.php', 'app/Api/Discovered/PingHandler.php', 'e2e.sh']" />

| Požiadavka | Odpoveď |
| --- | --- |
| 1. až 3. `/quote` s `X-Api-Key: key-a` | `200`, `RateLimit: "perKey";r=2`, 1, 0 |
| 4. `/quote` s `key-a` | `429` s `Retry-After` |
| 1. `/quote` s `key-b` | `200`, vlastná kvóta, `r=2` |
| `/quote` so zlým kľúčom | `401`, počíta ho len `perIp` |
| 3. `/ping` | `429`, `Retry-After: 30` |
| akýkoľvek `/status` | `200`, bez hlavičiek obmedzenia |

## Konfigurácia

`rateLimits` definuje pomenované politiky, `defaults.rateLimit` použije `perIp` na každý endpoint, `quote` pridá `perKey` a `status` limit vypne.

<<< @/../examples/rate-limit/config/common.neon{yaml}

## Handlery

<<< @/../examples/rate-limit/app/Api/QuoteHandler.php

`#[RateLimit]` na objavenom handleri nahradí predvolený limit vlastným:

<<< @/../examples/rate-limit/app/Api/Discovered/PingHandler.php

## Test

<<< @/../examples/rate-limit/e2e.sh{bash}

## Časté otázky

### Prečo sa počíta aj zlý kľúč?

`perIp` má kľúč podľa IP, takže beží pred autorizáciou a obmedzí aj klienta, ktorý háda kľúče. `perKey` má kľúč `identity`, takže beží až po úspešnej autorizácii. Pozrite si [Obmedzenie požiadaviek](/sk/guide/rate-limit#pred-autorizaciou-a-po-nej).

### Prečo tretí ping čaká 30 sekúnd?

`tokenBucket` s 2 za minútu doplní jeden token každých 30 sekúnd.

### Kde sú počítadlá?

V Nette cache v `temp/cache`. Pri viacerých serveroch použite `storage: redis`, pozrite si [Úložiská](/sk/guide/rate-limit#uloziska).

### Aké hlavičky klient dostane?

`RateLimit-Policy` a `RateLimit` pre každú použitú politiku a `Retry-After` pri `429`.

### Môžem obmedzovať podľa IP za proxy?

Áno, uveďte proxy v `http: proxy:`, inak všetci klienti zdieľajú jej kvótu. Pozrite si [Ktorá IP sa počíta?](/sk/guide/rate-limit#ktora-ip-sa-pocita).
