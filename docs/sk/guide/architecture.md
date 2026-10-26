---
title: Architektúra
description: 'Ako ApiPresenter v Nette API 3.x spracuje požiadavku: endpoint, obmedzenie požiadaviek, parametre, autorizácia, handler, kontrola výstupu a logovanie.'
---

# Architektúra

Každá API požiadavka ide do jedného presentera, `Tomaj\NetteApi\Presenters\ApiPresenter`. Ten sa spýta `ApiDecider`, ktorý endpoint sa zhoduje, spustí kontroly v pevnom poradí a vráti to, čo vytvoril handler alebo `error handler`.

<Diagram name="architecture-1" alt="Požiadavka, ApiDecider, obmedzenie požiadaviek, autorizácia, hlásenie chýb parametrov (400), handler, kontrola výstupnej schémy, odpoveď a logger, s výstupmi 429, 401 a 400 a 404 z DefaultHandlera (zalogovaná); každá odpoveď sa vráti klientovi" />

## Časti

| Časť | Trieda alebo rozhranie | Úloha |
| --- | --- | --- |
| Presenter | `ApiPresenter` | Spúšťa všetky kroky nižšie. Je `final`. |
| Zoznam endpointov | `ApiDecider` | `addApi()` registruje endpointy, `getApi()` nájde ten pre požiadavku. |
| Endpoint | `EndpointIdentifier` | Metóda, verzia, balík a voliteľná akcia. |
| Handler | `ApiHandlerInterface`, zvyčajne `BaseHandler` | Deklaruje parametre a výstupy, robí samotnú prácu. |
| Autorizácia | `ApiAuthorizationInterface` | Rozhodne, či požiadavka smie bežať. |
| Obmedzenie požiadaviek | `RateLimitInterface` | Voliteľné, pre každý endpoint. |
| Chyby | `ErrorHandlerInterface`, `DefaultErrorHandler` | Zmení zlyhania na JSON odpovede. |
| Kontrola výstupov | `ConfiguratorInterface` | Rozhodne, či sa výstupy validujú a zobrazujú detaily chýb. |
| Logger | `ApiLoggerInterface` | Voliteľný, hľadá sa v DI kontajneri. |

## Poradie krokov

Stručne: CORS hlavičky, nájdenie endpointu (`404`), obmedzenie požiadaviek (`429`), načítanie parametrov, autorizácia (`401`), chyby parametrov (`400`), handler, kontrola výstupu (`500`) a logovanie. Parametre sa načítajú pred autorizáciou, ich chyby sa však hlásia až po nej, takže volajúci bez prístupu dostane `401` a žiadne podrobnosti o parametroch.

Celé poradie s kódom a výstupom každého kroku je na jednej stránke: [Spracovanie v ApiPresenteri](/sk/guide/presenter).

<Diagram name="architecture-2" alt="Priebeh POST požiadavky: autorizácia, potom chyby parametrov (načítaných pred ňou) a handler, každý s chybovou vetvou cez error handler" />

::: warning Nie každá odpoveď sa loguje
Kroky obmedzenia požiadaviek, autorizácie a chýb parametrov skončia predčasne, takže `429`, `401`, `400` a `500` zo zlyhanej autorizácie sa v 3.x k loggeru nedostanú. `404`, odpovede handlera a chyby handlera alebo výstupu sa logujú.
:::

## Časté otázky

### Môžem ApiPresenter nahradiť vlastným presenterom?

Nie dedením, trieda je `final`. Môžete ho nakonfigurovať (`setCorsHeader()`) a vymeniť jeho závislosti: `error handler`, konfigurátor a logger. Pozrite si [Spracovanie chýb](/sk/guide/errors).

### Prečo požiadavka so zlým tokenom aj zlými parametrami vráti 401 a nie 400?

Autorizácia beží skôr, ako sa nahlásia chyby parametrov. Pozrite si krok 6 vyššie a stránku [Autorizácia](/sk/guide/authorization).

### Vytvára sa handler každého endpointu pri každej požiadavke?

Nie. Keď do `addApi()` odovzdáte názov triedy, handler sa vezme z DI kontajnera až pri volaní jeho endpointu. Pozrite si [Handlery](/sk/guide/handlers#registracia-handlera).

### Ako vyzerá odpoveď neznámeho endpointu?

`404` s `{"status":"error","message":"Unknown api endpoint"}`. Testuje to [príklad hello world](/sk/examples/hello-world).

### Kde nájdem všetky triedy v jednom zozname?

V [Referencii](/sk/api/reference).
