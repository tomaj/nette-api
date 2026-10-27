---
title: Výstupy a odpovede
description: Vracanie JSON, textu, XML alebo presmerovaní z handlerov Nette API, deklarácia outputs() s JSON schémami a voľba, kedy sa validujú.
---

# Výstupy a odpovede

`handle()` vracia objekt odpovede. `outputs()` opisuje odpovede, ktoré endpoint môže vrátiť, aby sa dali validovať a zdokumentovať.

## Odpovede

| Trieda | Konštruktor | Posiela |
| --- | --- | --- |
| `JsonApiResponse` | `(int $code, array\|JsonSerializable $payload, string $contentType = 'application/json', string $charset = 'utf-8', $expiration = null)` | JSON s `Content-Length` |
| `TextApiResponse` | `(int $code, $data)` | reťazec tak, ako je, alebo vykreslí Latte šablónu; content type nenastavuje |
| `XmlApiResponse` | `(int $code, string $data, $expiration = null)` | XML reťazec ako `text/xml` s `Content-Length` |
| `RedirectResponse` | `(string $url, int $httpCode = 302)` | presmerovanie |

Stavový kód, ktorý odovzdáte, dostane klient.

## XML, text a ďalšie formáty

Zvyčajne sa vracia JSON, ale handler môže vrátiť ľubovoľný formát:

```php
use Nette\Http\IResponse;
use Tomaj\NetteApi\Response\TextApiResponse;
use Tomaj\NetteApi\Response\XmlApiResponse;

// XML, posiela sa ako text/xml s Content-Length
return new XmlApiResponse(IResponse::S200_OK, '<?xml version="1.0"?><user><id>7</id><name>Ann</name></user>');

// Obyčajný text alebo Latte šablóna, vypíše sa tak, ako je
return new TextApiResponse(IResponse::S200_OK, "id,name\n7,Ann\n");
```

- `XmlApiResponse` berie hotový XML reťazec; zostavte ho cez `SimpleXMLElement`, `DOMDocument` alebo šablónu.
- `TextApiResponse` nenastavuje content type, takže platí predvolený (PHP `default_mimetype`, zvyčajne `text/html`). Pre CSV, YAML alebo súbory si ho nastavte vo vlastnej triede. Vstavaný `OpenApiHandler` posiela cez `TextApiResponse` svoj YAML výstup.
- Pre ľubovoľný iný formát napíšte triedu, ktorá implementuje `ResponseInterface`: Nette `IResponse` s `getCode()`.
- Triedu výstupu majú iba `JsonApiResponse` a `RedirectResponse`. So zapnutou validáciou musí endpoint s neprázdnym `outputs()` vrátiť jednu z nich, takže pri XML alebo textových endpointoch nechajte `outputs()` prázdne.

## Výstupy

`outputs()` vracia objekty `OutputInterface`:

| Trieda | Zhoduje sa s |
| --- | --- |
| `JsonOutput(int $code, string $schema, string $description = '')` | `JsonApiResponse` s týmto kódom, ktorej obsah zodpovedá JSON schéme |
| `RedirectOutput(int $code, string $description = '')` | `RedirectResponse` s týmto kódom |

Obe prijímajú `addExample($name, $value)` pre OpenAPI. [Príklad parametrov](/sk/examples/params-validation) má schémy v JSON súboroch:

<<< @/../examples/params-validation/app/Api/schema/user.json

## Kedy sa výstupy validujú

<Diagram name="outputs-1" alt="Ak je validateSchema() zapnuté, odpoveď musí zodpovedať výstupu s rovnakým kódom; inak handleSchema vráti 500" />

`ApiPresenter` sa spýta konfigurátora výstupu na `validateSchema()`. Keď je true, odpoveď musí zodpovedať aspoň jednému výstupu. Ak nezodpovedá žiadnemu, `handleSchema()` `error handlera` vráti `500 Internal server error`. Handler bez výstupov sa nekontroluje nikdy.

| Konfigurátor | Validuje a zobrazuje detaily chýb, keď |
| --- | --- |
| `DebuggerConfigurator` | Tracy nie je v produkčnom režime |
| `EnvConfigurator($envVariable = 'APP_ENV', $productionValue = 'production')` | premenná nemá produkčnú hodnotu |
| `QueryConfigurator($request, 'schema_validate', 'error_detail')` | validuje s `?schema_validate=1`, detaily zobrazí s `?error_detail=1` (čokoľvek okrem `0` a `false`) |

::: tip
Validácia stojí čas pri každej požiadavke. Bežné nastavenie kontroluje výstupy pri vývoji a v CI a v produkcii ju vynecháva. [Príklad logovania](/sk/examples/logging-errors) ukazuje oba režimy.
:::

## Časté otázky

### Moja odpoveď je správna, ale dostávam 500 Internal server error. Prečo?

Kontrola výstupu zlyhala. Pozrite sa na `detail` v odpovedi (pri zapnutých detailoch) alebo Tracy log. Každý kód, ktorý vraciate, potrebuje vlastný výstup. Pozrite si [Spracovanie chýb](/sk/guide/errors).

### Musím výstupy deklarovať?

Nie. Bez výstupov sa nič nevaliduje, ale [OpenAPI](/sk/guide/openapi) nemá čo ukázať ako schému odpovede.

### Môžem vracať chybové odpovede bez deklarácie?

Ak má endpoint výstupy, aj vrátená `404` musí jednému zodpovedať, inak validácia zlyhá. Pridajte `JsonOutput(404, ...)`.

### Ako pošlem súbor alebo HTML?

`TextApiResponse` vypíše ľubovoľný reťazec alebo vykreslí šablónu. Pre súbory napíšte malú triedu implementujúcu `ResponseInterface`: Nette `IResponse` s `getCode()`.

### Môžem použiť Fractal transformery?

Áno. `BaseHandler` vytvorí Fractal `Manager`, dostupný cez `$this->getFractal()`, a v konštruktore prijme voliteľný `ScopeFactoryInterface`. Pozrite si [Handlery](/sk/guide/handlers#metody).
