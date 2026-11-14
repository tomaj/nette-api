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

## Čo konzola posiela

Konzola zostaví požiadavku z parametrov handlera, či už pochádzajú z `params()` alebo z typovaného DTO, takže neexistuje osobitný režim pre DTO.

| Zdroj parametra | Posiela sa ako |
| --- | --- |
| query | query string |
| hodnota z cesty | URL-enkódovaná do segmentu `{name}` v URL |
| hodnota hlavičky | hlavička požiadavky |
| pole tela | prvý media type, ktorý handler číta a konzola ho vie zostaviť: JSON, URL-enkódovaný formulár alebo multipart |
| súbor | multipart upload; pri priloženom súbore idú polia tela v tej istej multipart požiadavke |

V tele JSON si každá hodnota zachová deklarovaný typ a vstup pre vnorený objekt berie JSON text, napríklad `{}` pre prázdny objekt. Vstup pre zoznam má jedno políčko na položku a položka, ktorá je objekt, berie JSON text. Typované pole, ktoré ostane prázdne, sa nepošle (prázdny reťazec nikdy nie je platné číslo, dátum, voľba, objekt ani položka zoznamu), takže nepoužité políčko zoznamu sa preskočí. Prázdne voliteľné textové pole sa tiež nepošle, takže server použije jeho predvolenú hodnotu; povinné textové pole sa pošle ako prázdny reťazec, aby sa dali vyskúšať jeho obmedzenia dĺžky a patternu. Prázdna hlavička sa nepošle nikdy. Prázdna hodnota z cesty sa neposiela vôbec: konzola ukáže `The path value 'id' is empty. Fill it in and send the request again.` namiesto volania URL s prázdnym segmentom. Číslo, ktoré JSON nezvládne, napríklad `1e999` alebo celé číslo mimo 64-bitového rozsahu, sa pošle ako text, takže server odpovie svojím bežným `400`.

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
