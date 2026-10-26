---
title: Webová konzola
description: Prehliadanie a volanie endpointov Nette API z prehliadača cez ApiListingControl a ApiConsoleControl, s vygenerovanými formulármi pre parametre a tokeny.
---

# Webová konzola

Dva Nette komponenty vám umožnia preklikať sa API: `ApiListingControl` vypíše endpointy a `ApiConsoleControl` vykreslí formulár pre jeden z nich, pošle požiadavku cez cURL a ukáže odpoveď.

::: danger Nie do produkcie
Konzola vie zavolať každý endpoint s ľubovoľným tokenom, ktorý napíšete. Presenter chráňte, alebo ho v produkcii vynechajte.
:::

## Presenter

<<< @/../examples/openapi-console/app/UI/Console/ConsolePresenter.php

Pohľad `default` (`default.latte`) vykreslí zoznam endpointov. Klik na endpoint presmeruje na `actionShow`, ktorá vykreslí `show.latte` s formulárom konzoly pre daný endpoint.

<<< @/../examples/openapi-console/app/UI/Console/default.latte

<<< @/../examples/openapi-console/app/UI/Console/show.latte

## Komponenty

| Komponent | Konštruktor | Poznámky |
| --- | --- | --- |
| `ApiListingControl` | `(ApiDecider)` | `onClick[]` dostane `method, version, package, apiAction` |
| `ApiConsoleControl` | `(IRequest, EndpointInterface, ApiHandlerInterface, ApiAuthorizationInterface, ?ApiLink, ?ApiConsoleFormFactoryInterface)` | `setTemplateFilePath()`, `setFormRenderer()` |

Formulár má jedno pole pre každý parameter, pole pre token alebo API kľúč (alebo meno a heslo pre Basic) a timeout. Ak ho chcete zmeniť, napíšte vlastný `ApiConsoleFormFactoryInterface`.

## Časté otázky

### Prečo odoslanie formulára zlyhá s chybou CSRF?

Nette formuláre prijmú POST len z tej istej stránky, s cookie `_nss`. Konzolu otvárajte v prehliadači, nie skriptom bez cookies.

### Môže konzola volať iný server?

Volá URL vo formulári, predvolene endpoint na aktuálnom hoste.

### Ako zmením vzhľad?

Použite `setTemplateFilePath()` s vlastnou Latte šablónou, alebo `setFormRenderer()`.

### Ukáže konzola požiadavku, ktorú poslala?

Áno, panel odpovede ukazuje URL, hlavičky, telo a čas.

### Potrebujem konzolu, keď mám OpenAPI?

Nie, sú nezávislé. [OpenAPI](/sk/guide/openapi) funguje s akýmkoľvek OpenAPI nástrojom.
