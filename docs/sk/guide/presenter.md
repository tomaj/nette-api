---
title: Spracovanie v ApiPresenteri
description: 'Presné poradie krokov v Nette API ApiPresenter::run(), od CORS a hľadania endpointu po autorizáciu, handler, kontrolu výstupu a logovanie, s kódom každého kroku.'
---

# Spracovanie v ApiPresenteri

`Tomaj\NetteApi\Presenters\ApiPresenter::run()` je celá požiadavka v jednej metóde. Táto stránka ju prechádza krok za krokom podľa zdrojového kódu 3.x.

<Diagram name="presenter-1" alt="run() pošle CORS hlavičky, nájde endpoint, overí obmedzenie požiadaviek, načíta parametre, overí autorizáciu, nahlási chyby parametrov, zavolá handler, overí výstup a zaloguje požiadavku; výstupy 404, 429, 401, 400 a 500" />

| Krok | Kód | Výstup |
| --- | --- | --- |
| 1. CORS hlavičky | `sendCorsHeaders()` | |
| 2. Endpoint | `$this->apiDecider->getApi(...)` | `404` cez `DefaultHandler` |
| 3. Obmedzenie požiadaviek | `checkRateLimit($rateLimit)` | `429`, neloguje sa |
| 4. Načítanie parametrov | `new ParamsProcessor($handler->params())` | |
| 5. Autorizácia | `checkAuth($authorization, $params)` | `401` alebo `500`, neloguje sa |
| 6. Chyby parametrov | `errorHandler->handleInputParams()` | `400`, neloguje sa |
| 7. Handler | `$handler->handle($params)` | výnimka: `500` |
| 8. Kontrola výstupu | `$output->validate($response)` | bez zhody: `500` |
| 9. Logger | `logRequest()` | |

## 1. CORS

Hlavičky idú von ako prvé, takže ich má aj `404` či `401` a prehliadač chybu prečíta. `setCorsAllowedOrigins()` má prednosť pred `setCorsHeader()`. Pozrite si [CORS](/sk/guide/cors).

## 2. Hľadanie endpointu

Metóda a parametre routy `version`, `package` a `apiAction` idú do `ApiDecider`. Neznáma URL dostane `DefaultHandler`, ktorý prejde zvyšnými krokmi, odpovie `404` a zaloguje sa. Pozrite si [ApiDecider a endpointy](/sk/guide/api-decider).

## 3. Obmedzenie požiadaviek

```php
$rateLimitResponse = $rateLimit->check();
// pridá X-RateLimit-Limit a X-RateLimit-Remaining
if ($remaining === 0) {
    // 429, Retry-After, vaša chybová odpoveď alebo "Too many requests. Retry after N seconds."
}
```

Pred autorizáciou, takže neúspešné prihlásenia sa rátajú. Pozrite si [Obmedzenie počtu požiadaviek](/sk/guide/rate-limit).

## 4. Načítanie parametrov

```php
$paramsProcessor = new ParamsProcessor($handler->params());
$params = $paramsProcessor->isError() ? [] : $paramsProcessor->getValues();
```

Každý parameter sa načíta a overí. Pri chybe je `$params` prázdne a chyba počká na krok 6.

## 5. Autorizácia

`authorized()`, ktoré vráti `false`, ide do `handleAuthorization()`: `401` s `getErrorMessage()`. Výnimka ide do `handleAuthorizationException()`: `500` so správou výnimky, takže do nej nedávajte tajomstvá. Pozrite si [Autorizácia](/sk/guide/authorization).

## 6. Chyby parametrov

Až teraz, po autorizácii, zlý vstup odpovie `400`:

```json
{"status": "error", "message": "wrong input", "detail": {"page": ["Field is required"]}}
```

`detail` sa ukáže, pokiaľ ho konfigurátor neskryje, ako to robí `EnvConfigurator` v produkcii. Pozrite si [Vstupné parametre](/sk/guide/params).

## 7. Handler

`handle($params)` beží v `try`. Každý `Throwable` ide do `ErrorHandlerInterface::handle()`, ktorý ho zapíše do Tracy a odpovie `500`. Pozrite si [Spracovanie chýb](/sk/guide/errors).

## 8. Kontrola výstupu

Keď `ConfiguratorInterface::validateSchema()` vráti true, odpoveď sa porovná s každým z `outputs()`; **stačí prvá zhoda**. Bez zhody ide do `handleSchema()` a `500`. Prázdne `outputs()` sa nekontroluje. Každý kód, ktorý handler vracia, potrebuje výstup, čo ukazuje [príklad stránkovania](/sk/examples/pagination) pre `400` a `404`. Pozrite si [Výstupy](/sk/guide/outputs).

## 9. Logovanie

Ak má kontajner `ApiLoggerInterface`, požiadavka sa zaloguje s konečným kódom a časom. Hlavičky zo `setMaskedLogHeaders()` sa zalogujú ako `***`. Kroky 3, 5 a 6 už skončili, takže `429`, `401` a `400` sa v 3.x nelogujú. Pozrite si [Logovanie](/sk/guide/logging).

## Nastavenie presentera

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setCorsAllowedOrigins(['https://app.example.com'])
			- setMaskedLogHeaders()
```

Toto je jediná služba `apiPresenter` zo stránky [Začíname](/sk/guide/getting-started#_3-zaregistrujte-sluzby): pridajte k nej tieto riadky `setup:`, alebo dajte blok pod rovnakým názvom do iného konfiguračného súboru a Nette ich zlúči. Druhú službu `ApiPresenter` neregistrujte; dve skončia chybou „Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found“.

Trieda je `final`. Správanie zmeníte cez tieto settery a cez služby, ktoré berie z kontajnera: `ErrorHandlerInterface`, `ConfiguratorInterface`, `ApiLoggerInterface` a `IpDetectorInterface`.

## Časté otázky

### Prečo zlý vstup so zlým tokenom odpovie 401?

Autorizácia (krok 5) beží pred chybami parametrov (krok 6).

### Prečo sa moja 400 zmení na 500?

Handler vrátil `400`, ale žiadny výstup s kódom `400` nezodpovedá. Pridajte ho, alebo v produkcii validáciu vypnite.

### Vidí obmedzenie požiadaviek autorizovaného používateľa?

Nie, beží pred autorizáciou. Klienta rozlíšte podľa kľúča alebo IP, ako [príklad obmedzenia požiadaviek](/sk/examples/rate-limit).

### Loguje sa čas odpovede aj pri obmedzení požiadaviek alebo autorizácii?

Iba pri logovaných požiadavkách: čas od začiatku `run()` po kontrolu výstupu.

### Môžem pridať krok, napríklad ID požiadavky?

Nie do `run()`. Urobte to v autorizácii, v handleri alebo v udalosti Nette `onRequest` / `onResponse`.
