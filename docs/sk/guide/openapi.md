---
title: OpenAPI
description: Publikovanie OpenAPI 3.0 dokumentu vášho Nette API cez OpenApiHandler - JSON alebo YAML, bezpečnostné schémy, chybové odpovede, initData a allowedEndpoints.
---

# OpenAPI

`OpenApiHandler` je bežný handler, ktorý vracia OpenAPI 3.0 dokument všetkých endpointov s rovnakou verziou, ako má on sám. Registruje sa ako ktorýkoľvek endpoint.

<Diagram name="openapi-1" alt="OpenApiHandler číta z ApiDecider parametre, výstupy, metadáta a autorizáciu každého endpointu a vráti JSON alebo YAML" />

<<< @/../examples/openapi-console/config/common.neon{yaml}

`OpenApiHandler` potrebuje `ApiDecider`, ktorý zase drží handlery, preto ho v `addApi()` registrujte názvom triedy. Vytvorí sa až pri volaní jeho endpointu.

## Čo sa dostane do dokumentu

| Z | Do |
| --- | --- |
| `summary()`, `description()`, `tags()`, `deprecated()` | operácie |
| `params()` | `parameters` a `requestBody` pre parametre tela, ako JSON a raw vstup |
| `outputs()` | `responses` s JSON schémami |
| trieda autorizácie | `securitySchemes` a `security`: `Bearer`, `Basic` alebo `apiKey` v query, hlavičke či cookie |
| parametre, autorizácia | `400`, keď sú parametre, `401`, ak nejde o `NoAuthorization`, a `500` vždy |

Endpointy s vlastnou triedou autorizácie sú bez `security`.

## Konštruktor

```php
new OpenApiHandler(
    ApiDecider $apiDecider,
    ApiLink $apiLink,
    Nette\Http\Request $request,
    array $initData = [],        // zlúči sa do dokumentu, napr. info.title
    array $allowedEndpoints = [] // cesta => metódy na zahrnutie, * v ceste; prázdne znamená všetky
)
```

`?format=yaml` vráti YAML, keď je nainštalovaný `symfony/yaml`; inak je povolený len `json`.

## Časté otázky

### Ako zmením názov „Nette API"?

Odovzdajte `initData: [info: [title: 'My API']]`. Rekurzívne sa zlúči s vygenerovaným dokumentom.

### Môžem skryť interné endpointy?

Áno, verejné vymenujte v `allowedEndpoints`, alebo zaregistrujte druhý `OpenApiHandler` pre inú verziu.

### Prečo chýbajú endpointy verzie 2?

Dokument pokrýva len verziu endpointu `OpenApiHandler`. Zaregistrujte jeden pre každú verziu; pozrite si [Odkazy a verzie](/sk/guide/links#verzie).

### Môžem dokument použiť so Swagger UI?

Áno, nasmerujte Swagger UI na URL endpointu. Knižnica má aj vlastnú [webovú konzolu](/sk/guide/console).

### Je OpenAPI endpoint verejný?

Len ak ho zaregistrujete s `NoAuthorization`, ako to robí [príklad OpenAPI](/sk/examples/openapi-console). Funguje akákoľvek [autorizácia](/sk/guide/authorization).
