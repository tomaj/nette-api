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
    array $allowedEndpoints = [], // vzor cesty => metódy na zahrnutie; prázdne znamená všetky
    ?SpecBuilder $specBuilder = null // vlastný builder, pozri nižšie
)
```

`?format=yaml` vráti YAML, keď je nainštalovaný `symfony/yaml`; inak je povolený len `json`.

## allowedEndpoints

Kľúče (alebo hodnoty v zozname) sú vzory, ktoré sa bez ohľadu na veľkosť písmen porovnávajú s cestou endpointu bez verzie (napr. `users/list`); hodnoty obmedzujú HTTP metódy, napr. `['users' => ['get', 'post'], 'orders/list' => 'get']`.

::: warning
Vzor sa nájde kdekoľvek v ceste, takže `users` zahrnie aj `admin/users-internal`, a `*` nie je zástupný znak. Používajte dostatočne jedinečné vzory a vygenerovaný dokument skontrolujte, ak je verejný. Pozri [#211](https://github.com/tomaj/nette-api/issues/211).
:::

## Buildery

Dokument vytvárajú malé buildery v `Tomaj\NetteApi\OpenApi`, každý zodpovedá za jednu časť:

| Trieda | Vytvára |
|---|---|
| `SpecBuilder` | celý dokument: info, servers, components, paths (filtruje API podľa verzie) |
| `PathBuilder` | cesty a operácie (summary, tagy, parametre, telo requestu, security, odpovede) |
| `ParameterBuilder` | query a cookie parametre |
| `RequestBodyBuilder` | JSON, raw, form a multipart telá requestu |
| `ResponseBuilder` | chybové odpovede a ich schémy, odpovede z outputov handlera |
| `SecuritySchemeBuilder` | security schémy a požiadavky z autorizácií (prepíšte `createScheme()`) |

`SchemaRegistry` (transformuje JSON schémy a zbiera `definitions` do `components/schemas`) a `EndpointFilter` (`allowedEndpoints`) sú pomocné triedy vytvárané pre každý generovaný dokument.

### Výmena buildera cez DI

Builder rozšírte a zaregistrujte v DI spolu s `PathBuilder` a `SpecBuilder`. `OpenApiHandler` dostane `SpecBuilder` cez autowiring, inak sa použijú predvolené buildery. Linky vždy vytvára `ApiLink` handlera.

```php
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;

class MyParameterBuilder extends ParameterBuilder
{
    public function build(ApiHandlerInterface $handler): array
    {
        $parameters = parent::build($handler);
        $parameters[] = ['name' => 'X-Request-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string']];
        return $parameters;
    }
}
```

```neon
services:
	- MyParameterBuilder
	- Tomaj\NetteApi\OpenApi\PathBuilder
	- Tomaj\NetteApi\OpenApi\SpecBuilder
```

## Časté otázky

### Ako zmením názov „Nette API"?

Odovzdajte `initData: [info: [title: 'My API']]`. Rekurzívne sa zlúči s vygenerovaným dokumentom.

### Môžem skryť interné endpointy?

Áno, verejné vymenujte v `allowedEndpoints` (pozor na [pravidlá porovnávania](#allowedendpoints)), alebo zaregistrujte druhý `OpenApiHandler` pre inú verziu.

### Prečo chýbajú endpointy verzie 2?

Dokument pokrýva len verziu endpointu `OpenApiHandler`. Zaregistrujte jeden pre každú verziu; pozrite si [Odkazy a verzie](/sk/guide/links#verzie).

### Môžem dokument použiť so Swagger UI?

Áno, nasmerujte Swagger UI na URL endpointu. Knižnica má aj vlastnú [webovú konzolu](/sk/guide/console).

### Je OpenAPI endpoint verejný?

Len ak ho zaregistrujete s `NoAuthorization`, ako to robí [príklad OpenAPI](/sk/examples/openapi-console). Funguje akákoľvek [autorizácia](/sk/guide/authorization).
