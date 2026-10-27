---
title: 'Príklad: CORS'
description: 'Spustiteľný príklad Nette API pre frontend na inom origine: pevný Access-Control-Allow-Origin a preflight OPTIONS, na ktorý odpovedá CorsPreflightHandler.'
---

# Príklad: CORS

API poznámok pre frontend na `http://localhost:5173`: `GET` a JSON `POST` na `/api/v1/notes`.

<ProjectSource name="cors" :files="['config/common.neon', 'app/Api/NoteCreateHandler.php', 'e2e.sh']" />

<Diagram name="examples-cors-1" alt="Prehliadač na localhost:5173 pošle OPTIONS, dostane 200 s CORS hlavičkami, potom pošle JSON POST a dostane 201" />

::: tip Viac originov
Príklad používa jeden pevný origin, ktorý funguje v 3.5.0 aj neskôr. Pre viac originov pribudlo vo verzii 3.6.0 `setCorsAllowedOrigins()`. Pozrite si [CORS](/sk/guide/cors#povolene-originy).
:::

## Služby

<<< @/../examples/cors/config/common.neon{yaml}

## Handler

<<< @/../examples/cors/app/Api/NoteCreateHandler.php

## Test

<<< @/../examples/cors/e2e.sh{bash}

## Časté otázky

### Prečo frontend potrebuje preflight?

`POST` s `Content-Type: application/json` nie je jednoduchá požiadavka, takže prehliadač sa najprv opýta cez `OPTIONS`.

### Prečo je Content-Type v Allow-Headers?

Predvolené preflight hlavičky povoľujú len `Authorization` a `X-Requested-With`; JSON telo potrebuje `Content-Type`.

### Čo robí Max-Age 600?

Prehliadač si odpoveď na preflight pamätá 10 minút.

### Kde nastavím origin pre jednotlivé prostredia?

`frontendOrigin` je NEON parameter; prepíšte ho v lokálnej alebo produkčnej konfigurácii.

### Funguje OPTIONS na neznámu URL?

Nie, vráti `404`, ako ukazuje test. Pozrite si [CORS](/sk/guide/cors#preflight-poziadavky).
