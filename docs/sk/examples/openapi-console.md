---
title: 'Príklad: OpenAPI a konzola'
description: 'Spustiteľný príklad Nette API, ktorý publikuje OpenAPI 3.0 dokument v JSON a YAML, JSON zoznam endpointov a webovú konzolu s Latte tagom {apiLink}.'
---

# Príklad: OpenAPI a konzola

Malé API knižnice kníh zdokumentované tromi spôsobmi: `GET /api/v1/openapi`, `GET /api/v1/endpoints` a webová konzola na `/console`.

<ProjectSource name="openapi-console" :files="['config/common.neon', 'app/Api/BookListHandler.php', 'app/UI/Console/ConsolePresenter.php', 'app/UI/Console/default.latte', 'app/UI/Console/show.latte', 'e2e.sh']" />

<Diagram name="examples-openapi-console-1" alt="OpenApiHandler, ApiListingHandler aj presenter konzoly čítajú endpointy z ApiDecider" />

## Služby

<<< @/../examples/openapi-console/config/common.neon{yaml}

## Zdokumentovaný handler

<<< @/../examples/openapi-console/app/Api/BookListHandler.php

Summary, popis, tagy, parametre aj výstupy skončia v OpenAPI dokumente.

## Konzola

<<< @/../examples/openapi-console/app/UI/Console/ConsolePresenter.php

Pohľad `default` (`default.latte`) vykreslí zoznam endpointov. Klik na endpoint presmeruje na `actionShow`, ktorá vykreslí `show.latte` s formulárom konzoly pre daný endpoint.

<<< @/../examples/openapi-console/app/UI/Console/default.latte

<<< @/../examples/openapi-console/app/UI/Console/show.latte

## Test

<<< @/../examples/openapi-console/e2e.sh{bash}

## Časté otázky

### Prečo je dostupný YAML?

Príklad vyžaduje `symfony/yaml`. Bez neho je povolený len `?format=json`. Pozrite si [OpenAPI](/sk/guide/openapi).

### Ako dokument vie, že POST /books potrebuje token?

Prečíta `BearerTokenAuthorization` a pridá bezpečnostnú schému `Bearer`.

### Má byť konzola verejná?

Nie, chráňte ju, alebo ju v produkcii vynechajte. Pozrite si [Webovú konzolu](/sk/guide/console).

### Čo potrebuje {apiLink}?

Latte rozšírenie a filter `apiLink`, oboje v konfigurácii vyššie. Pozrite si [Odkazy](/sk/guide/links#tag-apilink-v-latte).

### Prečo si test drží cookie jar?

Nette formuláre pred prijatím POST kontrolujú same-site cookie `_nss`.
