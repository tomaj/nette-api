---
title: 'Príklad: verzie API'
description: 'Spustiteľný príklad Nette API s endpointom vo v1 a v2, v1 zastaraná v OpenAPI s hlavičkami Deprecation a Link a jeden OpenAPI dokument pre každú verziu.'
---

# Príklad: verzie API

`GET /api/v1/users` vráti `name`, `GET /api/v2/users` vráti `first_name` a `last_name`. v1 stále funguje a hovorí klientom, aby prešli na novšiu.

<ProjectSource name="versions" :files="['config/common.neon', 'app/Api/UserV1Handler.php', 'app/Api/UserV2Handler.php', 'e2e.sh']" />

<Diagram name="examples-versions-1" alt="ApiDecider vyberie handler v1 alebo v2 podľa verzie v URL; v1 pridá hlavičky Deprecation a Link; každá verzia má vlastný OpenAPI dokument; v3 odpovie 404" />

| Požiadavka | Odpoveď |
| --- | --- |
| `/api/v1/users?id=7` | `200 {"id":"7","name":"Ada Lovelace"}` s `Deprecation: true` a `Link` na v2 |
| `/api/v2/users?id=7` | `200 {"id":"7","first_name":"Ada","last_name":"Lovelace"}` |
| `/api/v3/users?id=7` | `404 Unknown api endpoint` |
| `/api/v1/openapi.json` | iba endpointy v1, v1 označená ako zastaraná |

## Verzia 1

`deprecated()` vráti `true`, čo ukáže OpenAPI. Hlavičky pridá `Nette\Http\IResponse`.

<<< @/../examples/versions/app/Api/UserV1Handler.php

## Verzia 2

<<< @/../examples/versions/app/Api/UserV2Handler.php

## Služby

Verzia je druhý argument `EndpointIdentifier`.

<<< @/../examples/versions/config/common.neon{yaml}

## Test

<<< @/../examples/versions/e2e.sh{bash}

## Časté otázky

### Kde je verzia v URL?

Routa `api/v<version>/<package>[/<apiAction>]` ju dá do cesty; `ApiDecider` ju porovná spolu s metódou a balíkom. Pozrite si [ApiDecider a endpointy](/sk/guide/api-decider).

### Môže v2 použiť kód v1?

Áno, oba handlery môžu používať tú istú službu; tu sa líši iba tvar odpovede.

### Čo robia hlavičky Deprecation a Link?

Povedia klientovi alebo gateway, že v1 je zastaraná a kde je nástupca, bez toho, aby ju rozbili.

### Prečo jeden OpenAPI dokument na verziu?

`OpenApiHandler` vypíše endpointy svojej verzie. Klienti v2 nevidia v1. Pozrite si [OpenAPI](/sk/guide/openapi).

### Funguje verzia ako reťazec?

Áno, `EndpointIdentifier` prijme reťazec ako `'1.1'` a predvolený vzor routy prijme bodku v `/api/v1.1/...`.
