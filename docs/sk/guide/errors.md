---
title: Spracovanie chýb
description: Ako Nette API mení výnimky, zlý vstup, zlyhanú autorizáciu a pokazené výstupy na JSON cez DefaultErrorHandler a ako napísať vlastný ErrorHandlerInterface.
---

# Spracovanie chýb

`ApiPresenter` chybové odpovede nikdy nevytvára sám. Zavolá službu `ErrorHandlerInterface`, zvyčajne `DefaultErrorHandler`.

## Rozhranie a predvolené odpovede

| Metóda | Volá sa, keď | DefaultErrorHandler odpovie |
| --- | --- | --- |
| `handle(Throwable, array $params)` | `handle()` vyhodil výnimku | `500 Internal server error`, loguje do Tracy |
| `handleInputParams(array $errors)` | parametre sú neplatné | `400 wrong input` |
| `handleSchema(array $errors, array $params)` | kontrola výstupu zlyhala | `500 Internal server error`, loguje do Tracy |
| `handleAuthorization(ApiAuthorizationInterface, array $params)` | `authorized()` je false | `401` s `getErrorMessage()` |
| `handleAuthorizationException(Throwable, array $params)` | `authorized()` vyhodil výnimku | `500` s textom výnimky |

Všetky vracajú `JsonApiResponse` so `status: error` a `message`.

## Detaily chýb

`DefaultErrorHandler(ConfiguratorInterface)` pridá `detail`, keď je `showErrorDetail()` konfigurátora true: text výnimky pre `500`, chyby jednotlivých polí pre `400`, chyby schémy pre pokazený výstup.

```json
{"status":"error","message":"Internal server error","detail":"Database connection refused"}
```

Ktorý konfigurátor použiť, opisujú [Výstupy](/sk/guide/outputs#kedy-sa-vystupy-validuju). V produkcii majú byť detaily vypnuté.

## Vlastný `error handler`

`DefaultErrorHandler` je `final`, takže ho obaľte. [Príklad logovania](/sk/examples/logging-errors) zmení jednu výnimku na `503` a zvyšok nechá predvolenému:

<<< @/../examples/logging-errors/app/AppErrorHandler.php

```yaml
services:
	defaultErrorHandler:
		factory: Tomaj\NetteApi\Error\DefaultErrorHandler
		autowired: false
	- App\AppErrorHandler(@defaultErrorHandler)
```

`autowired: false` zabezpečí, že na autowiring ostane jediný `ErrorHandlerInterface`.

## Časté otázky

### Prečo produkcia vráti 400 bez toho, aby povedala, ktoré pole je zlé?

`DefaultErrorHandler` skryje `detail` pre všetky kódy, keď je `showErrorDetail()` false, vrátane `400`. `DefaultErrorHandler` je `final`, preto implementujte `ErrorHandlerInterface`, všetky metódy delegujte na obalený `DefaultErrorHandler` ako v časti [Vlastný `error handler`](#vlastny-error-handler) a v `handleInputParams()` vráťte `new JsonApiResponse(400, ['status' => 'error', 'message' => 'wrong input', 'detail' => $errors])`.

### Ako pošlem 404 pre chýbajúci záznam?

Vráťte ho z handlera: `new JsonApiResponse(404, [...])`. Error handlery sú na neočakávané zlyhania. Pozrite si [Handlery](/sk/guide/handlers).

### Kde uvidím výnimku?

V adresári logov Tracy, `exception.log` a HTML súbor pre každú výnimku. [Príklad logovania](/sk/examples/logging-errors) to kontroluje.

### Môžu chyby autorizácie mať vlastný stavový kód?

Rozhoduje váš `error handler`; `handleAuthorization()` dostane objekt autorizácie a môže z neho čítať čokoľvek. Pozrite si [Autorizáciu](/sk/guide/authorization).

### Je pokazený výstup chybou v produkcii?

Len ak tam konfigurátor výstupy validuje. S `EnvConfigurator` a `APP_ENV=production` sa odpoveď pošle bez kontroly.
