---
title: Logovanie
description: Logovanie požiadaviek Nette API cez ApiLoggerInterface - čo dostane log(), ktoré odpovede sa v 3.x logujú a ako maskovať tajné hlavičky.
---

# Logovanie

Keď má DI kontajner službu implementujúcu `Tomaj\NetteApi\Logger\ApiLoggerInterface`, `ApiPresenter` ju zavolá po behu handlera.

<Diagram name="logging-1" alt="Odpoveď handlera, alebo odpoveď error handlera po výnimke, sa odovzdá do ApiLoggerInterface::log" />

::: warning Maskujte tajné hlavičky
Predvolene dostane logger hlavičky požiadavky presne tak, ako prišli, vrátane `Authorization`, cookies a API kľúčov. Od verzie 3.6.0 zavolajte na presenteri [`setMaskedLogHeaders()`](#maskovanie-hlaviciek). V 3.5.0 ich maskujte vo svojom loggeri.
:::

## Rozhranie

```php
public function log(
    int $responseCode,
    string $requestMethod,
    string $requestHeader,
    string $requestUri,
    string $requestIp,
    string $requestAgent,
    int $responseTime
): bool;
```

| Argument | Hodnota |
| --- | --- |
| `$responseCode` | odoslaný stavový kód |
| `$requestHeader` | všetky hlavičky požiadavky, jedna `Name: value` na riadok |
| `$requestUri` | `REQUEST_URI` aj s query stringom |
| `$requestIp` | zo služby `IpDetectorInterface`, ktorá musí existovať |
| `$responseTime` | milisekundy |

## Príklad

[Príklad logovania](/sk/examples/logging-errors) zapisuje JSON riadky:

<<< @/../examples/logging-errors/app/FileApiLogger.php

```yaml
services:
	- Tomaj\NetteApi\Misc\IpDetector
	- App\FileApiLogger(%appDir%/../log/api.log)
```

## Maskovanie hlavičiek

::: tip Od verzie 3.6.0
`ApiPresenter::setMaskedLogHeaders()` nahradí hodnoty zvolených hlavičiek požiadavky reťazcom `***` skôr, než sa dostanú do `log()`. Kým ju nezavoláte, je vypnutá, takže logovanie v 3.x sa samo nezmení.
:::

```yaml
services:
	apiPresenter:
		factory: Tomaj\NetteApi\Presenters\ApiPresenter
		setup:
			- setMaskedLogHeaders()
```

Toto je jediná služba `apiPresenter` zo stránky [Začíname](/sk/guide/getting-started#_3-zaregistrujte-sluzby): pridajte k nej tieto riadky `setup:`, alebo dajte blok pod rovnakým názvom do iného konfiguračného súboru a Nette ich zlúči. Druhú službu `ApiPresenter` neregistrujte; dve skončia chybou „Multiple services of type Tomaj\NetteApi\Presenters\ApiPresenter found“.

| Volanie | Maskované hlavičky |
| --- | --- |
| `setMaskedLogHeaders()` | `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key` (`ApiPresenter::DEFAULT_MASKED_LOG_HEADERS`) |
| `setMaskedLogHeaders(['X-Session', 'Authorization'])` | iba uvedené, zoznam nahradí predvolený |
| `setMaskedLogHeaders([])` | žiadne |

Názvy sa porovnávajú bez ohľadu na veľkosť písmen, takže `x-api-key` zodpovedá `X-Api-Key`. Hlavička v logu ostane aj s názvom, iba hodnota sa zmení na `***`:

```text
Authorization: ***
X-Trace: abc-123
```

Maskujú sa iba hlavičky. Token v query stringu, napríklad `?api_key=` pre `QueryApiKeyAuthentication`, ostane v `$requestUri`; odstráňte ho vo svojom loggeri.

## Čo sa loguje

| Odpoveď | Loguje sa v 3.x |
| --- | --- |
| výsledok handlera, ľubovoľný kód | áno |
| `500` z výnimky alebo zlyhanej kontroly výstupu | áno |
| `404 Unknown api endpoint` | áno |
| `400` zlý vstup, `401`, `429`, `500` z výnimky v `authorized()` | nie, presenter skončí pred loggerom |

## Časté otázky

### Prečo v logu chýbajú moje odpovede 401?

V 3.x končia predčasne, pozrite tabuľku. Ak ich potrebujete, logujte ich vo vlastnom [`error handleri`](/sk/guide/errors).

### Potrebujem na logovanie detektor IP?

Áno, presenter berie `IpDetectorInterface` z kontajnera. Uprednostnite taký, ktorý číta len `REMOTE_ADDR`; pozrite si [Autorizáciu](/sk/guide/authorization#tokeny-a-obmedzenie-ip).

### Kam idú výnimky?

Do Tracy, cez `Debugger::log()` v `DefaultErrorHandler`. Pozrite si [Spracovanie chýb](/sk/guide/errors).

### Môžem logovať telo odpovede?

Cez toto rozhranie nie, dostane len kód. Ak potrebujete viac, obaľte handler alebo `error handler`.

### Záleží na návratovej hodnote log()?

Nie, presenter ju ignoruje.

### Mám maskovanie nechať aj vo vlastnom loggeri?

Pre hlavičky nie, keď voláte `setMaskedLogHeaders()`. Nechajte ho pre tokeny v query stringu, na ktoré presenter nesiaha.
