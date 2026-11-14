---
title: Vstupné parametre
description: Deklarácia a validácia vstupu v Nette API - GET, POST, PUT, cookie, súbor, raw a JSON telo, typy, povinné a povolené hodnoty, viac hodnôt a príklady.
---

# Vstupné parametre

`params()` vracia vstupy endpointu. `ApiPresenter` ich všetky načíta a zvaliduje pred spustením `handle()`, ktorý dostane hodnoty podľa názvu.

Vstup sa dá deklarovať dvoma rovnocennými spôsobmi: cez `params()`, ako popisuje táto stránka, alebo ako properties typovaného DTO, ktoré číta `TypedHandler::handleDto()`. Oba vytvoria tieto objekty `InputParam` a zdieľajú validáciu, OpenAPI aj konzolu. Pri výbere pomôže [Typovaný vstup (DTO)](/sk/guide/typed-input) a [params() a DTO vedľa seba](/sk/guide/input-styles) ukazuje tie isté endpointy v oboch štýloch.

<Diagram name="params-1" alt="Vstup načíta InputParam::getValue, prevedie na deklarovaný typ a zvaliduje; chyby vrátia 400, platné hodnoty idú do handlera" />

## Triedy parametrov

| Trieda | Číta z | Poznámky |
| --- | --- | --- |
| `GetInputParam` | query string | podporuje typy a `setMulti()` |
| `PostInputParam` | telo formulára, `$_POST` | podporuje typy a `setMulti()` |
| `PutInputParam` | form-encoded telo ľubovoľnej metódy | bez prevodu typu, typ sa ale kontroluje: `INTEGER` odmietne aj `"12"`, použite `STRING` alebo žiadny typ |
| `PathInputParam` | pomenovaný `{placeholder}` v URL endpointu | vždy povinný, pozrite [nižšie](#parametre-path-header-a-body) |
| `HeaderInputParam` | HTTP hlavička, bez ohľadu na veľkosť písmen | pozrite [nižšie](#parametre-path-header-a-body) |
| `BodyInputParam` | jedno pole tela JSON, formulára alebo multipart | pozrite [nižšie](#parametre-path-header-a-body) |
| `CookieInputParam` | cookie | bez prevodu typu, typ sa ale kontroluje: `INTEGER` odmietne aj `"12"`, použite `STRING` alebo žiadny typ |
| `FileInputParam` | nahraný súbor, `$_FILES` | `setMulti()` pre viac súborov |
| `RawInputParam` | celé telo požiadavky ako reťazec | `setMulti()` vyhodí výnimku |
| `JsonInputParam` | celé telo požiadavky dekódované ako JSON | potrebuje JSON schému; `setMulti()` vyhodí výnimku |

Druhý argument konštruktora prvých dvoch je typ, prípad enumu `Tomaj\NetteApi\Validation\InputType` alebo jeho reťazcová konštanta:

```php
new GetInputParam('limit', InputType::Integer);
new GetInputParam('limit', InputType::INTEGER); // reťazec 'integer', funguje rovnako
```

`$param->getParamType()` vráti, odkiaľ sa parameter číta, ako enum `Params\ParamType` (`getType()` stále vracia reťazec).

## Nastavenia

Všetky parametre majú spoločné fluent API:

| Metóda | Účinok |
| --- | --- |
| `setRequired()` | `null` alebo `''` vráti `Field is required`. |
| `setDefault($value)` | použije sa, keď vstup chýba |
| `setAvailableValues([...])` | prejdú len tieto hodnoty, inak `Field contains not available value(s)`. Pri asociatívnom poli sú hodnotami kľúče, hodnoty poľa sú popisky pre konzolu. |
| `setMulti()` | očakáva pole, `?id[]=1&id[]=2`; typy a povolené hodnoty platia pre každú položku |
| `setDescription()` | zobrazí sa v OpenAPI a v konzole |
| `addExample($name, $value)` | zobrazí sa v OpenAPI a predvyplní v konzole |

## Typy

| Typ | Prevod |
| --- | --- |
| `INTEGER`, `DOUBLE`, `FLOAT` | číselné reťazce sa prevedú |
| `BOOLEAN` | `1`, `true` (bez ohľadu na veľkosť písmen) sú `true`, všetko ostatné `false` |
| `STRING` | ostane, ako je |
| `ARRAY` | musí byť pole |

::: warning Hodnota, ktorá nie je číslo, sa zmení na null
V 3.x `?limit=abc` s typom `INTEGER` nie je chyba: hodnota sa pred validáciou prevedie na `null`, takže voliteľný parameter prejde a povinný vráti `Field is required`. V `handle()` vždy počítajte s `null`. Testuje to [príklad parametrov](/sk/examples/params-validation).
:::

## Parametre Path, Header a Body

`PathInputParam` číta hodnotu z URL endpointu. Placeholder deklarujte v akcii endpointu ako `{name}`, napríklad `users/{id}/detail`, ako popisuje [Typovaný vstup](/sk/guide/typed-input#hodnoty-z-cesty-a-sablony-url). Parameter je vždy povinný a jeho placeholder v OpenAPI má rovnaký názov. `HeaderInputParam` číta hlavičku požiadavky bez ohľadu na veľkosť písmen. `BodyInputParam` číta jedno pole objektu JSON, URL-enkódovaného formulára alebo multipart tela a `setContentTypes()` uvádza media typy, ktoré prijíma. Typované DTO vygeneruje tieto isté tri triedy z `#[Path]`, `#[Header]` a `#[Body]` a `params()` ich vie deklarovať priamo:

```php
public function params(): array
{
    return [
        (new PathInputParam('id', InputType::INTEGER))->setRequired(),
        new HeaderInputParam('X-Request-Id', InputType::STRING),
        (new BodyInputParam('email', InputType::STRING))
            ->setRequired()
            ->setContentTypes(['application/json', 'application/x-www-form-urlencoded']),
    ];
}
```

OpenAPI ich zapíše ako `in: path`, `in: header` a ako polia `requestBody`.

## JSON telo

`JsonInputParam` berie JSON schému ako reťazec a voči nej validuje dekódované telo:

<<< @/../examples/params-validation/app/Api/UserCreateHandler.php{35-37}

Pokazený JSON vráti `400` s hláškou parsera, chýbajúce povinné telo vráti `missing data`.

## Odpoveď 400

So zapnutými detailmi chýb (pozrite si [Spracovanie chýb](/sk/guide/errors)) sú chyby vypísané pre každý parameter:

```json
{"status":"error","message":"wrong input","detail":{"status":["Field contains not available value(s)"]}}
```

S vypnutými detailmi má odpoveď len `status` a `message`.

## Časté otázky

### Ako načítam JSON telo bez schémy?

Použite `RawInputParam` a dekódujte ho sami, alebo do `JsonInputParam` odovzdajte voľnú schému, napríklad `{"type":"object"}`.

### Prečo je môj celočíselný parameter null namiesto 400?

Nečíselné hodnoty sa v 3.x menia na `null`, pozrite upozornenie vyššie. Označte parameter ako povinný alebo kontrolujte `null`.

### Môže parameter prijať viac hodnôt?

Áno, `setMulti()` a potom pošlite `name[]=a&name[]=b`. Pozrite si [príklad parametrov](/sk/examples/params-validation).

### Kde sa zobrazia príklady, ktoré pridám?

V OpenAPI dokumente a ako predvyplnené hodnoty vo [webovej konzole](/sk/guide/console).

### Môže autorizácia čítať parametre?

Načítané hodnoty nie. `authorized()` nemá argumenty; hodnotu si prečítajte z HTTP požiadavky sami, ako to robí `QueryApiKeyAuthentication`. Parametre sa načítajú pred autorizáciou, ale dostane ich len `error handler`, viac v [Spracovaní v ApiPresenteri](/sk/guide/presenter).
