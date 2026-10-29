---
title: Problem Details (RFC 9457)
description: 'Posielanie chýb Nette API ako RFC 9457 problem details (application/problem+json) cez ProblemDetailsErrorHandler, výber podľa Accept a popis v OpenAPI.'
---

# Problem Details (RFC 9457)

::: tip Odporúčané pre nové projekty, od verzie 4.0.0
Problem details sú odporúčaný formát chýb: RFC 9457 je štandard, takže každá chyba má rovnaký strojovo čitateľný tvar, ktorému klienti aj nástroje rozumejú. Treba ich zapnúť a nie sú predvolené, lebo existujúci klienti čítajú `message` z formátu 3.x zo [Spracovania chýb](/sk/guide/errors), ktorý funguje ďalej bez akejkoľvek zmeny.
:::

[RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) je štandardné JSON telo pre chyby HTTP API, posielané ako `application/problem+json`:

```json
{"type":"https://api.example.com/problems/validation-error","status":400,"title":"Bad Request","detail":"Request parameters are not valid.","errors":[{"parameter":"product","detail":"Field is required"}]}
```

| Člen | Význam |
| --- | --- |
| `type` | URI, ktoré určuje druh problému, `about:blank`, keď je to len HTTP stav |
| `status` | HTTP stavový kód, rovnaký ako v odpovedi |
| `title` | krátky popis druhu, rovnaký pre každý výskyt |
| `detail` | tento výskyt, pre človeka |
| `instance` | URI tohto výskytu, nepovinné |
| čokoľvek iné | rozširujúce členy, napríklad `errors`, `limit`, `retryAfter` |

## Zapnutie

Zaregistrujte `ProblemDetailsErrorHandler` namiesto `DefaultErrorHandler`, na to isté miesto v `config/api.neon` zo stránky [Začíname](/sk/guide/getting-started#_3-zaregistrujte-sluzby):

```yaml
services:
	# nahrádza - Tomaj\NetteApi\Error\DefaultErrorHandler
	- Tomaj\NetteApi\Error\ProblemDetailsErrorHandler(typeBaseUri: 'https://api.example.com/problems/')
```

Nechajte len jednu službu error handlera: pri dvoch Nette DI nevie autowirovať `ErrorHandlerInterface` a kontajner spadne s chybou „Multiple services of type“. Použije output configurator, ktorý už máte, rovnako ako `DefaultErrorHandler`. Ak `typeBaseUri` nekončí lomkou, doplní sa. Bez `typeBaseUri` alebo s prázdnym je každý `type` `about:blank`.

## Ako vyzerá ktorá chyba

| Volá sa pre | Stav | `type` | `detail` | Rozšírenia |
| --- | --- | --- | --- | --- |
| neplatné parametre, `handleInputParams()` | `400` | `validation-error` | `Request parameters are not valid.` | `errors`, vždy |
| `authorized()` je false, `handleAuthorization()` | `401` | `unauthorized` | `getErrorMessage()` | |
| neznámy endpoint, `handleNotFound()` | `404` | `not-found` | `Unknown api endpoint` | |
| vyčerpaný limit, `handleRateLimit()` | `429` | `rate-limited` | `Too many requests.`, ak je čas známy, aj `Retry after N seconds.` | `limit`, `retryAfter`, ak je známy |
| výnimka v handleri, `handle()` | `500` | `internal-error` | text výnimky, len so zapnutými detailmi | |
| výnimka v `authorized()`, `handleAuthorizationException()` | `500` | `internal-error` | text výnimky, len so zapnutými detailmi | |
| výstup nesedí, `handleSchema()` | `500` | `invalid-output` | žiadny | `outputErrors`, len so zapnutými detailmi |

`type` je `typeBaseUri` a za ním skratka. Každá výnimka sa zaloguje do Tracy.

Chyby validácie sú zoznam `{"parameter": ..., "detail": ...}` a posielajú sa aj v produkcii, lebo klient ich potrebuje na opravu požiadavky. Texty výnimiek a chyby výstupnej schémy sa posielajú, len keď je `showErrorDetail()` true. Chyby výstupnej schémy idú do `outputErrors`, jeden zoznam za každý deklarovaný výstup, takže `errors` má vždy tvar uvedený vyššie.

## Vlastné problémy z handlera

Biznis chybu, napríklad objednávku vypredaného produktu, vráti handler ako `ProblemDetailsResponse`:

```php
use Tomaj\NetteApi\Error\ProblemDetails;
use Tomaj\NetteApi\Response\ProblemDetailsResponse;

return new ProblemDetailsResponse(new ProblemDetails(
    status: 409,
    title: 'Out of stock',
    detail: 'Product sold-out has 0 pieces left, 2 requested.',
    type: 'https://api.example.com/problems/out-of-stock',
    instance: '/api/v1/orders',
    extensions: ['product' => 'sold-out', 'available' => 0],
));
```

`ProblemDetails` prijme len kódy `4xx` a `5xx` a odmietne rozšírenia s názvom `type`, `status`, `title`, `detail` alebo `instance`. `ProblemDetailsResponse` dedí od `JsonApiResponse`, preto jej kód uveďte v `outputs()` ako pri každej odpovedi; schéma je `ResponseBuilder::problemDetailsSchema()`. Pozrite si [príklad](/sk/examples/problem-details).

## Len pre klientov, ktorí o ne žiadajú

`NegotiatingErrorHandler` ponechá formát 3.x a problem details pošle, len keď hlavička `Accept` obsahuje `application/problem+json`, čo pomôže počas prechodu klientov:

```yaml
services:
	# nahrádza - Tomaj\NetteApi\Error\DefaultErrorHandler
	- Tomaj\NetteApi\Error\NegotiatingErrorHandler(
		Tomaj\NetteApi\Error\DefaultErrorHandler()
		Tomaj\NetteApi\Error\ProblemDetailsErrorHandler(typeBaseUri: 'https://api.example.com/problems/')
	)
```

`application/problem+json;q=0` sa nepočíta. `*/*`, `application/*` a `application/json` dostanú formát 3.x. Každá odpoveď error handlera dostane `Vary: Accept` (popri `Vary: Origin` zo [zoznamu povolených originov CORS](/sk/guide/cors#povolene-originy)), aby cache a CDN oba formáty nezamieňali.

OpenAPI špecifikácia opisuje len jeden formát chýb: predvolene formát 3.x, s `ResponseBuilder(problemDetails: true)` problem details.

## OpenAPI

`ResponseBuilder(problemDetails: true)` pridá schému `ProblemDetails` a odpovede `400`, `401` a `500` opíše ako `application/problem+json`. Odovzdajte ho službe `OpenApiHandler`, ktorú už máte zo stránky [OpenAPI](/sk/guide/openapi), vedľa jej `initData`:

```yaml
services:
	# tá istá služba OpenApiHandler, pridaný specBuilder
	- Tomaj\NetteApi\Handlers\OpenApiHandler(
		initData: [info: [title: 'Orders API']]
		specBuilder: Tomaj\NetteApi\OpenApi\SpecBuilder(
			Tomaj\NetteApi\OpenApi\PathBuilder(responseBuilder: Tomaj\NetteApi\OpenApi\ResponseBuilder(problemDetails: true))
		)
	)
```

Nechajte len jednu službu `OpenApiHandler`: `addApi()` ju hľadá podľa názvu triedy, a ak sú dve, endpoint OpenAPI spadne s chybou „Multiple services of type“. Odpovede, ktoré handler uvádza v `outputs()`, napríklad `409` vyššie, sú opísané ako `application/json`, lebo `JsonOutput` nemá content type. Pozrite si [OpenAPI](/sk/guide/openapi).

## Časté otázky

### Mám používať problem details?

Áno, v novom projekte. RFC 9457 dáva každej chybe rovnaký strojovo čitateľný tvar s `type`, `status`, `title` a `detail`, takže klienti rozlíšia chyby bez parsovania textu. V existujúcom API s klientmi, ktorí čítajú `message`, začnite s `NegotiatingErrorHandler` a prepnite, keď klienti posielajú `Accept: application/problem+json`.

### Musím v 4.0 prejsť na problem details?

Nie. `DefaultErrorHandler` ostáva predvolený a jeho telá sú rovnaké ako v 3.x. Keď chcete RFC 9457, zaregistrujte `ProblemDetailsErrorHandler` alebo `NegotiatingErrorHandler`.

### Prečo produkcia pošle chyby validácie, ale nie text výnimky?

Chyby validácie opisujú vlastnú požiadavku klienta a pomôžu ju opraviť. Text výnimky môže obsahovať hostiteľov, SQL alebo cesty, preto sa posiela, len keď je `showErrorDetail()` true, a vždy sa zaloguje do Tracy.

### Prečo sa môj problém 409 zmenil na 500?

Pri vývoji sa výstupy validujú a kód, ktorý nie je v `outputs()`, ide do `handleSchema()`. Pridajte do výstupov handlera `new JsonOutput(409, Json::encode(ResponseBuilder::problemDetailsSchema()))`.

### Kam má smerovať URI v type?

Na stránku, ktorá problém vysvetľuje vývojárovi. Nemusí existovať, ale nesmie sa meniť, lebo podľa neho klienti problémy rozlišujú. Bez `typeBaseUri` je type `about:blank`.

### Môžem do problému pridať vlastné členy?

Áno, cez `extensions` v `ProblemDetails`. Pošlú sa vedľa štandardných členov; päť štandardných názvov sa odmietne.
