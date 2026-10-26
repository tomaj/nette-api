---
title: 'Príklad: hello world'
description: 'Najmenší spustiteľný projekt Nette API - jeden GET endpoint s voliteľným parametrom name, router, služby a end-to-end test.'
---

# Príklad: hello world

Jeden endpoint, `GET /api/v1/hello?name=Ada`, ktorý odpovedá `{"message":"Hello Ada!"}`.

<ProjectSource name="hello-world" :files="['app/Api/HelloHandler.php', 'app/RouterFactory.php', 'config/common.neon', 'e2e.sh']" />

<Diagram name="examples-hello-world-1" alt="curl zavolá GET /api/v1/hello?name=Ada, router ho odovzdá ApiPresenter, HelloHandler odpovie 200 so správou" />

## Handler

<<< @/../examples/hello-world/app/Api/HelloHandler.php

`name` je voliteľný s predvolenou hodnotou `world`, takže `GET /api/v1/hello` odpovie `{"message":"Hello world!"}`.

## Routa

<<< @/../examples/hello-world/app/RouterFactory.php

## Služby

<<< @/../examples/hello-world/config/common.neon{yaml}

## Test

<<< @/../examples/hello-world/e2e.sh{bash}

## Časté otázky

### Prečo POST /api/v1/hello vracia 404?

Endpoint je zaregistrovaný len pre `GET`. Metóda je súčasťou endpointu, pozrite si [Handlery](/sk/guide/handlers#registracia-handlera).

### Prečo DebuggerConfigurator?

Zobrazuje detaily chýb a validuje výstupy, keď je Tracy vo vývojovom režime. Pozrite si [Výstupy](/sk/guide/outputs#kedy-sa-vystupy-validuju).

### Odkiaľ sa berie JSON?

`JsonApiResponse` zakóduje pole a nastaví `Content-Type: application/json`. Pozrite si [Výstupy a odpovede](/sk/guide/outputs#odpovede).

### Môžem pridať druhý endpoint?

Pridajte ďalší riadok `addApi()` s novým `EndpointIdentifier`. [Príklad parametrov](/sk/examples/params-validation) má dva.

### Čo robí každý krok nastavenia?

[Začíname](/sk/guide/getting-started) vysvetľuje tie isté súbory krok za krokom.
