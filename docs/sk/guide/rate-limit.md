---
title: Obmedzenie požiadaviek
description: Obmedzenie počtu volaní endpointu Nette API cez RateLimitInterface, RateLimitResponse, hlavičky X-RateLimit a 429 Too Many Requests s Retry-After.
---

# Obmedzenie požiadaviek

Obmedzenie požiadaviek sa nastavuje voliteľným štvrtým argumentom `addApi()`. Knižnica definuje rozhranie a odpoveď; ako požiadavky počítate (Redis, databáza, súbor), je na vás.

<Diagram name="rate-limit-1" alt="ApiPresenter zavolá check(); remaining 0 vráti 429 s Retry-After, inak sa pridajú hlavičky obmedzenia požiadaviek a spustí sa handler" />

## Rozhranie

```php
interface RateLimitInterface
{
    public function check(): ?RateLimitResponse;
}
```

`check()` beží prvý, pred parametrami aj autorizáciou. Vráťte `null` pre „tu bez limitu" alebo `RateLimitResponse`:

```php
new RateLimitResponse(int $limit, int $remaining, ?int $retryAfter = null, ?IResponse $errorResponse = null)
```

| Výsledok | Odpoveď |
| --- | --- |
| `null` | nič sa nepridá, požiadavka pokračuje |
| `remaining` > 0 | hlavičky `X-RateLimit-Limit` a `X-RateLimit-Remaining`, požiadavka pokračuje |
| `remaining` = 0 | `429`, tie isté hlavičky plus `Retry-After` a `{"status":"error","message":"Too many requests. Retry after N seconds."}` alebo vaša `$errorResponse`. Pri `retryAfter` `null` ide prázdna hlavička `Retry-After` a správa `Retry after  seconds.` bez čísla |

Endpointy bez obmedzenia požiadaviek dostanú `NoRateLimit`, ktorý vracia `null`.

## Príklad

[Príklad autorizácie](/sk/examples/auth) počíta požiadavky podľa IP v súbore, 3 za minútu:

<<< @/../examples/auth/app/FileRateLimit.php

Zaregistrujte ho s `autowired: false`. Inak ho Nette DI autowiruje ako štvrtý argument každého volania `addApi()` v setupe:

```yaml
services:
	rateLimit:
		factory: App\FileRateLimit(directory: %tempDir%)
		autowired: false
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'limited'), App\Api\WhoAmIHandler, Tomaj\NetteApi\Authorization\NoAuthorization(), @rateLimit)
```

## Časté otázky

### Vie obmedzenie požiadaviek, ktorý token volal?

`check()` nedostane žiadne argumenty, lebo beží pred autorizáciou. Injectnite `Nette\Http\IRequest` a prečítajte si hlavičku alebo IP sami.

### Prečo majú zrazu všetky moje endpointy obmedzenie požiadaviek?

Služba obmedzenia požiadaviek sa autowiruje do `addApi()`. Nastavte `autowired: false`, ako je uvedené vyššie.

### Logujú sa odpovede 429?

Nie, v 3.x presenter skončí skôr, ako sa spustí logger. Pozrite si [Logovanie](/sk/guide/logging).

### Môžem vrátiť vlastné telo 429?

Áno, odovzdajte ho ako štvrtý argument `RateLimitResponse`.

### Podľa ktorej IP mám počítať?

Použite detektor IP, ktorý sa nedá podvrhnúť; pozrite upozornenie v [Autorizácii](/sk/guide/authorization#tokeny-a-obmedzenie-ip).
