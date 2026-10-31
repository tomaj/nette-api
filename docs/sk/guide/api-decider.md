---
title: ApiDecider a endpointy
description: Ako ApiDecider v Nette API registruje endpointy cez addApi(), hľadá podľa metódy, verzie, balíka a akcie, vytvára handlery až pri volaní a neznáme URL vráti 404.
---

# ApiDecider a endpointy

`Tomaj\NetteApi\ApiDecider` je zoznam endpointov. `ApiPresenter` sa ho raz za požiadavku spýta, ktorý handler, autorizáciu a obmedzenie požiadaviek použiť.

<Diagram name="api-decider-1" alt="Parametre routy a HTTP metóda idú do getApi; ApiDecider ich porovná s každým zaregistrovaným EndpointIdentifier; prvá zhoda vráti handler, autorizáciu a obmedzenie požiadaviek; bez zhody vráti DefaultHandler s 404" />

## Registrácia endpointov

```php
public function addApi(
    EndpointInterface $endpointIdentifier,
    ApiHandlerInterface|string $handler,
    ApiAuthorizationInterface $apiAuthorization,
    RateLimiterInterface|RateLimitInterface|null $rateLimit = null
): self
```

V NEON:

```yaml
services:
	apiDecider:
		factory: Tomaj\NetteApi\ApiDecider
		setup:
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 1, 'users'), App\Api\UserListHandler, Tomaj\NetteApi\Authorization\NoAuthorization())
			- addApi(Tomaj\NetteApi\EndpointIdentifier('POST', 1, 'users'), '@userCreateHandler', @bearer, @writeLimit)
			- addApi(Tomaj\NetteApi\EndpointIdentifier('GET', 2, 'users', 'detail'), App\Api\UserDetailV2Handler(), @bearer)
```

## EndpointIdentifier

| Argument | Príklad | Poznámka |
| --- | --- | --- |
| metóda | `'GET'` | porovnáva sa veľkými písmenami |
| verzia | `1`, `'1.1'` | uloží sa ako reťazec, nesmie obsahovať `/` |
| balík | `'users'` | prvý segment cesty za verziou |
| apiAction | `'detail'` alebo `null` | voliteľný druhý segment |

`getUrl()` vráti `v1/users/detail`, čo používa [OpenAPI](/sk/guide/openapi) aj konzola.

## Ako sa hľadá zhoda

`getApi($method, $version, $package, $apiAction)`:

1. zmení metódu na veľké písmená a prázdnu `apiAction` na `null`;
2. prechádza endpointy **v poradí, v akom boli pridané**;
3. vráti prvý, kde sa zhoduje metóda, verzia, balík aj akcia;
4. nastaví handleru endpoint, takže fungujú `getEndpoint()` a `createLink()`;
5. bez zhody vráti `DefaultHandler` a `NoAuthorization`, ktoré odpovedia `404 Unknown api endpoint` cez metódu `handleNotFound()` error handlera.

`GET /api/v1/users` a `POST /api/v1/users` sú dva endpointy s dvoma handlermi. Nezaregistrovaná verzia, napríklad `/api/v3/users`, je `404`, ako testuje [príklad verzií](/sk/examples/versions).

## Tri spôsoby, ako odovzdať handler

| Hodnota | Vytvorí sa |
| --- | --- |
| `App\Api\UserListHandler` (názov triedy) | až pri volaní endpointu, `getByType()` z kontajnera |
| `'@userCreateHandler'` (názov služby) | až pri volaní, `getByName()` |
| `App\Api\UserDetailV2Handler()` (objekt) | pri vytvorení decidera |

Názov triedy alebo služby udrží začiatok každej požiadavky rýchly, pretože sa vytvorí iba volaný handler a jeho závislosti. Viac v [Handleroch](/sk/guide/handlers#registracia-handlera).

Na úvodzovkách záleží. `'@userCreateHandler'` v úvodzovkách je obyčajný reťazec, názov služby, ktorý `ApiDecider` vyhľadá až pri volaní endpointu. Bez úvodzoviek, ako pri `@bearer` a `@writeLimit` vyššie, Nette vloží priamo službu, takže sa vytvorí spolu s deciderom. Pre malé objekty autorizácie a obmedzenia požiadaviek to nevadí; pre handlery s ťažkými závislosťami použite názov v úvodzovkách alebo názov triedy.

## Preflight pre všetky endpointy

`enableGlobalPreflight()` odpovie na `OPTIONS` pre každú zaregistrovanú URL cez `CorsPreflightHandler`, bez `addApi()` pre každý endpoint. Pozrite si [CORS](/sk/guide/cors#preflight-poziadavky).

## Zoznam endpointov

`getApis()` vráti každý endpoint s handlerom, autorizáciou a obmedzením požiadaviek. Stavia na ňom `OpenApiHandler` aj `ApiListingControl`. Pozor, vytvorí pri tom každý handler.

## Časté otázky

### Čo sa stane s dvoma rovnakými endpointmi?

Vyhrá prvý pridaný; druhý sa nikdy nezavolá.

### Porovnáva sa verzia ako číslo?

Nie, ako reťazec. `1` a `'1'` sa zhodujú, `'1.0'` sa s `1` nezhoduje.

### Môže balík obsahovať lomku?

Nie, routa berie jeden segment. Pre druhú úroveň použite `apiAction`.

### Môžem použiť inú štruktúru URL?

Áno, zmeňte routu, pokiaľ naplní `version`, `package` a voliteľne `apiAction`.

### Prečo sa môj handler vytvára pri každej požiadavke?

Bol odovzdaný ako objekt, `App\Api\Handler()`, alebo sa volalo `getApis()`. Odovzdajte radšej názov triedy.
