---
title: CORS
description: Ako Nette API posiela Access-Control-Allow-Origin cez setCorsHeader() alebo zoznam setCorsAllowedOrigins() a ako odpovedá na preflight požiadavky OPTIONS.
---

# CORS

Aplikácia v prehliadači na inom origine potrebuje CORS hlavičky. `ApiPresenter` ich pridá ku každej odpovedi a `ApiDecider` vie odpovedať na preflight `OPTIONS` požiadavky.

<Diagram name="cors-1" alt="Prehliadač pošle OPTIONS, dostane Access-Control hlavičky, potom pošle POST a dostane 201 s Access-Control-Allow-Origin" />

::: warning Namiesto 'auto' použite zoznam originov
`'auto'` dôveruje hlavičke `Referer` a povoľuje credentials pre ľubovoľnú stránku. Použite [`setCorsAllowedOrigins()`](#povolene-originy) (pribudlo vo verzii 3.6.0) alebo jeden pevný origin. `'auto'` v 3.x funguje ďalej rovnako a vo 4.0 sa plánuje odstrániť ([#186](https://github.com/tomaj/nette-api/issues/186), [milník 4.0.0](https://github.com/tomaj/nette-api/milestone/9)).
:::

## Access-Control-Allow-Origin

Každá odpoveď dostane `Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS` a k tomu jedno z nasledujúceho podľa `ApiPresenter::setCorsHeader()`:

| Hodnota | Posielané hlavičky |
| --- | --- |
| `'*'` (predvolené; s [DI rozšírením](/sk/guide/di-extension#cors) je predvolené `'off'`) | `Access-Control-Allow-Origin: *` |
| `'https://app.example.com'` | `Access-Control-Allow-Origin: https://app.example.com` |
| `'auto'` | schéma, host a port z hlavičky `Referer` (každý uvedený port okrem `80` ostane, teda aj `:443`) a `Access-Control-Allow-Credentials: true`; bez Referer nič |
| `'off'` | žiadna `Access-Control-Allow-Origin` |

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsHeader('https://app.example.com')
```

V PHP môžete namiesto preddefinovaných reťazcov odovzdať enum `Presenters\CorsMode`: `CorsMode::Any` (`'*'`), `CorsMode::Auto` (`'auto'`) a `CorsMode::Off` (`'off'`).

Toto je jediná služba `apiPresenter` zo stránky [Začíname](/sk/guide/getting-started#_3-zaregistrujte-sluzby): pridajte k nej tieto riadky `setup:`, alebo dajte blok pod rovnakým názvom do iného konfiguračného súboru a Nette ich zlúči. Druhú službu `ApiPresenter` neregistrujte; dve skončia chybou „Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found“.

## Povolené originy

::: tip Od verzie 3.6.0
Toto je odporúčané nastavenie. Na 3.5.0 namiesto toho použite jeden pevný origin.
:::

`setCorsAllowedOrigins(array $origins, bool $allowCredentials = false)` porovná hlavičku `Origin` požiadavky so zoznamom. Keď je nastavený, má prednosť pred `setCorsHeader()`.

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsAllowedOrigins(['https://app.example.com', 'http://localhost:3000'], true)
```

| Požiadavka | Posielané hlavičky |
| --- | --- |
| `Origin` je v zozname | `Access-Control-Allow-Origin` s týmto originom, `Vary: Origin` a `Access-Control-Allow-Credentials: true`, ak je druhý argument `true` |
| iný `Origin` alebo žiadny | iba `Vary: Origin`, takže prehliadač odpoveď zablokuje |

Porovnanie ignoruje veľkosť písmen a predvolené porty sa vynechajú (`https://app.example.com:443` zodpovedá `https://app.example.com`). Každý origin musí mať tvar `scheme://host[:port]`: bez cesty, bez lomky na konci, bez `*` či `null` a medzinárodné domény v punycode. Čokoľvek iné alebo prázdny zoznam vyhodí `InvalidArgumentException`.

Pri prechode z `'auto'` vypíšte originy, ktoré API naozaj volajú (frontendy a hosty pre lokálny vývoj), a `setCorsHeader('auto')` nahraďte `setCorsAllowedOrigins([...], true)`. `true` nechajte, len ak sa spoliehate na cookies alebo `withCredentials`.

## Preflight požiadavky

Pred `POST` s JSON telom alebo hlavičkou `Authorization` pošle prehliadač `OPTIONS` na tú istú URL. `enableGlobalPreflight()` naň odpovie pre každý zaregistrovaný endpoint:

<<< @/../examples/cors/config/common.neon{yaml}

`CorsPreflightHandler` pridá hlavičky, ktoré mu dáte, a odpovie `200` s `[]`. Bez argumentov pošle `Access-Control-Allow-Headers: Authorization` a `X-Requested-With`. `OPTIONS` na neznámu URL stále vráti `404`.

## Časté otázky

### Je 'auto' bezpečné?

Nie pre API, ktoré používajú cookies: ľubovoľná stránka, ktorú prihlásený používateľ navštívi, môže volať API s jeho cookies a čítať odpoveď. Použite pevný origin alebo [zoznam povolených originov](#povolene-originy).

### Ako povolím viac originov?

Cez [`setCorsAllowedOrigins()`](#povolene-originy). Na 3.5.0, ktoré zoznam nemá, vyberte jeden origin, alebo použite `'off'` a hlavičky pridávajte vo webovom serveri.

### Preflight mi vracia 404. Prečo?

Zavolajte `enableGlobalPreflight()` a overte, že existuje endpoint s rovnakou verziou, balíkom a akciou. [Príklad CORS](/sk/examples/cors) testuje oboje.

### Potrebujem CORS pre volania server-server?

Nie, kontrolujú ho len prehliadače.

### Posiela sa CORS hlavička aj pri chybách?

Áno, pridáva sa pred všetkým ostatným, takže ju majú aj `401`, `404` a `500`. Pozrite si [Architektúru](/sk/guide/architecture).
