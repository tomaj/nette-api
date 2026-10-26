---
title: 'Príklad: parametre a validácia'
description: 'Spustiteľný príklad Nette API s query parametrami, povolenými a viacerými hodnotami, celými číslami a JSON telom kontrolovaným schémou, vrátane odpovedí 400.'
---

# Príklad: parametre a validácia

Zoznam používateľov s filtrami, `GET /api/v1/users`, a vytvorenie používateľa s JSON telom, `POST /api/v1/users`.

<ProjectSource name="params-validation" :files="['app/Api/UserListHandler.php', 'app/Api/UserCreateHandler.php', 'app/Api/schema/user-create.json', 'config/common.neon', 'e2e.sh']" />

<Diagram name="examples-params-validation-1" alt="Query parametre a JSON telo kontroluje InputValidator a JsonSchemaValidator; neplatný vstup vráti 400, platný sa dostane do handlera" />

## Query parametre

<<< @/../examples/params-validation/app/Api/UserListHandler.php

- `status` povoľuje len `active` alebo `inactive`; `?status=deleted` vráti `400`.
- `id` má viac hodnôt, `?id[]=2&id[]=3`.
- `limit` je celé číslo. `?limit=abc` v 3.x **nie je** chyba: hodnota sa zmení na `null` a handler použije 10. Pozrite si [Parametre](/sk/guide/params#typy).

## JSON telo

<<< @/../examples/params-validation/app/Api/UserCreateHandler.php

<<< @/../examples/params-validation/app/Api/schema/user-create.json

Chýbajúci `email`, pokazený JSON alebo prázdne telo vráti `400`.

## Test

<<< @/../examples/params-validation/e2e.sh{bash}

## Časté otázky

### Čo obsahuje odpoveď 400?

`{"status":"error","message":"wrong input"}` a pri zapnutých detailoch chyby jednotlivých polí v `detail`. Pozrite si [Parametre](/sk/guide/params#odpoved-400).

### Prečo sú deklarované aj výstupy?

Zoznam aj vytvorený používateľ sa pri vývoji kontrolujú voči `user-list.json` a `user.json`. Pozrite si [Výstupy](/sk/guide/outputs).

### Ako urobím parameter povinným?

Zavolajte `setRequired()`. Chýbajúca hodnota vráti `400` s `Field is required`.

### Môžem namiesto JSON validovať POST formulár?

Pre každé pole použite `PostInputParam`. Pozrite si [Parametre](/sk/guide/params#triedy-parametrov).

### Zobrazia sa schémy v OpenAPI?

Áno, ako schémy tela požiadavky a odpovede. Pozrite si [OpenAPI](/sk/guide/openapi).
