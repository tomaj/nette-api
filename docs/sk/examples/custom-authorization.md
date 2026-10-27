---
title: 'Príklad: vlastná autorizácia'
description: 'Spustiteľný príklad Nette API s vlastným ApiAuthorizationInterface: tokeny s rolami, používateľ v handleri a token alebo API kľúč na jednom endpointe.'
---

# Príklad: vlastná autorizácia

Tri endpointy chránené autorizačnými triedami napísanými pre aplikáciu: ľubovoľný používateľ, iba administrátori a používateľ alebo API kľúč partnera.

<ProjectSource name="custom-authorization" :files="['config/common.neon', 'app/RoleTokenAuthorization.php', 'app/AnyOfAuthorization.php', 'app/CurrentUser.php', 'app/Api/MeHandler.php', 'e2e.sh']" />

<Diagram name="examples-custom-authorization-1" alt="Bearer token sa vyhľadá, overí sa rola, používateľ sa uloží do CurrentUser a handler ho prečíta; neznámy token alebo chýbajúca rola odpovie 401" />

| Požiadavka | Odpoveď |
| --- | --- |
| `GET /api/v1/me` bez tokenu | `401 Missing bearer token` |
| `GET /api/v1/me` s `Bearer bob-token` | `200 {"name":"Bob","roles":["staff"]}` |
| `GET /api/v1/report` s `Bearer bob-token` | `401 Role admin is required` |
| `GET /api/v1/report` s `Bearer alice-token` | `200` |
| `GET /api/v1/any/me` s `X-API-Key: partner-key` | `200`, klient s API kľúčom |

## Služby

Každá autorizácia je samostatná služba s `autowired: false`, takže jedna trieda slúži viacerým endpointom s rôznymi nastaveniami.

<<< @/../examples/custom-authorization/config/common.neon{yaml}

## Autorizácia

`authorized()` beží pred handlerom. Vráti `false` so správou pre `401`, alebo uloží používateľa a vráti `true`.

<<< @/../examples/custom-authorization/app/RoleTokenAuthorization.php

## Ktorákoľvek z viacerých

<<< @/../examples/custom-authorization/app/AnyOfAuthorization.php

## Odovzdanie používateľa handleru

`CurrentUser` je zdieľaná služba: autorizácia do nej zapíše, handler z nej číta.

<<< @/../examples/custom-authorization/app/CurrentUser.php

<<< @/../examples/custom-authorization/app/Api/MeHandler.php

## Test

<<< @/../examples/custom-authorization/e2e.sh{bash}

## Časté otázky

### Kedy beží authorized()?

Po obmedzení požiadaviek a po načítaní parametrov, pred chybami parametrov a pred handlerom. Pozrite si [Spracovanie v ApiPresenteri](/sk/guide/presenter).

### Prečo handler neoveruje rolu?

O tom, kto smie endpoint volať, rozhoduje jeho registrácia, takže ten istý handler môže byť raz verejný a inde iba pre administrátorov. Pozrite si [Vrstvy](/sk/guide/layers).

### Prečo hash_equals() v cykle?

Trvá rovnako dlho bez ohľadu na to, aká časť tokenu je správna, takže čas odpovede nepomáha pri hádaní. Pozrite si [Autorizácia](/sk/guide/authorization#tokeny-a-obmedzenie-ip).

### Čo obsahuje telo 401?

`{"status":"error","message":...}` s textom z `getErrorMessage()`. `AnyOfAuthorization` spojí správy všetkých neúspešných kontrol.

### Ako ukážem token v OpenAPI a v konzole?

`OpenApiHandler` rozozná len vstavané triedy (`instanceof`). Zdeďte jednu z nich, napríklad `BearerTokenAuthorization`, alebo odovzdajte vlastné `securitySchemes` v `initData` handlera; vlastná trieda tak či tak nedostane `security` pri operáciách. Pozrite si [OpenAPI](/sk/guide/openapi).
