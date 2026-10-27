---
title: 'Príklad: JSON a surové telo'
description: 'Spustiteľný príklad Nette API, ktorý číta JSON telo požiadavky overené JSON schémou cez JsonInputParam a CSV telo cez RawInputParam.'
---

# Príklad: JSON a surové telo

`POST /api/v1/orders` prijme JSON objednávku overenú schémou. `PUT /api/v1/products/import` prijme CSV ako surové telo.

<ProjectSource name="json-body" :files="['config/common.neon', 'app/Api/schema/order.json', 'app/Api/OrderCreateHandler.php', 'app/Api/CsvImportHandler.php', 'e2e.sh']" />

<Diagram name="examples-json-body-1" alt="JsonInputParam dekóduje telo a overí ho voči order.json; chyba syntaxe alebo schémy odpovie 400; RawInputParam odovzdá CSV text handleru" />

| Telo | Odpoveď |
| --- | --- |
| platná objednávka | `201 {"customer":"Ada","lines":2,"pieces":5}` |
| žiadne | `400`, `missing data` |
| `{"customer":` | `400 Syntax error` |
| `quantity: 0` alebo neznáme pole | `400` s chybami schémy |
| CSV s hlavičkou `sku,price` | `200 {"imported":2,"products":[...]}` |
| prázdny PUT | `400 Field is required` |
| CSV s inou hlavičkou | `422` z handlera |

## Schéma

<<< @/../examples/json-body/app/Api/schema/order.json{json}

## JSON handler

`$params['order']` je dekódované pole; handler beží iba vtedy, keď schéma prešla.

<<< @/../examples/json-body/app/Api/OrderCreateHandler.php

## Handler pre surové telo

<<< @/../examples/json-body/app/Api/CsvImportHandler.php

## Služby

<<< @/../examples/json-body/config/common.neon{yaml}

## Test

<<< @/../examples/json-body/e2e.sh{bash}

## Časté otázky

### Musí klient poslať Content-Type: application/json?

Nie, `JsonInputParam` číta `php://input` bez ohľadu na hlavičku. Pošlite ju aj tak, konzola aj OpenAPI to robia.

### Môže mať jeden endpoint JSON telo aj query parametre?

Áno, pridajte `GetInputParam` vedľa `JsonInputParam`. Zmysel má iba jeden parameter tela na endpoint, telo sa číta raz.

### Ako odmietnem neznáme polia?

`"additionalProperties": false` v schéme, ako v `order.json`. Pozrite si [Vstupné parametre](/sk/guide/params).

### Kde overím veci, ktoré schéma nevie?

V handleri, ako kontrolu hlavičky CSV, ktorá odpovie `422`. Každý kód, ktorý vraciate, potrebuje výstup, pozrite si [Výstupy](/sk/guide/outputs).

### Ukáže sa schéma v OpenAPI?

Áno, JSON schéma `JsonInputParam` sa stane telom požiadavky endpointu. Pozrite si [OpenAPI](/sk/guide/openapi).
