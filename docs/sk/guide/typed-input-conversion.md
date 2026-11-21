---
title: 'Typovaný vstup: konverzia, castery a validačné obmedzenia'
description: 'Ako typované DTO v Nette API striktne konvertuje hodnoty, spúšťa vlastné castery a validuje cez Range, Length, Pattern, Email a vlastné validačné obmedzenia.'
---

# Typovaný vstup: konverzia, castery a validačné obmedzenia

Táto stránka popisuje, čo sa stane s hodnotou medzi požiadavkou a property DTO: striktná konverzia, `#[Cast]` a atribúty validačných obmedzení. Základy sú na stránke [Typovaný vstup (DTO)](/sk/guide/typed-input).

## Striktná konverzia

Typovaný vstup sa nespolieha na voľné PHP pretypovania skalárov.

| Cieľový typ | Akceptovaný vstup |
| --- | --- |
| `string` | reťazec, ktorý je platné UTF-8; hodnota z query, formulára, hlavičky alebo cesty s inými bajtmi je `400` `Value must be valid UTF-8` |
| `int` | celé číslo alebo reťazec len s voliteľným znamienkom a číslicami, v rozsahu PHP integer |
| `float` | integer/float alebo kompletný desatinný či vedecký číselný reťazec; NaN a nekonečno sa odmietnu |
| `bool` | boolean `true`/`false`, integer `1`/`0`, reťazec `'true'`/`'false'`/`'1'`/`'0'` (bez ohľadu na veľkosť písmen) |
| backed enum | presne jedna z jeho backing hodnôt; enumy s integer hodnotami používajú pravidlá konverzie integer |
| `DateTimeImmutable` | RFC 3339 reťazec s dátumom, časom a explicitným offsetom, napríklad `2026-11-06T14:30:00+01:00` (`T` a `Z` môžu byť malé písmená; priestupná sekunda ako `23:59:60` sa odmietne, pretože ju PHP nevie vyjadriť) |
| `array` | zoznam, ktorého každá položka zodpovedá typu z PHPDoc |

Kontrola UTF-8 platí len pre typovaný vstup; handler s `params()` naďalej prijíma akékoľvek bajty.

`?page=1a` pre integer je `400`, nikdy `1` ani `null`. Biele znaky sa neodstraňujú a neznáme reťazce sa nemenia na `false`. JSON čísla a booleany si zachovajú typy, takže `1.0` ani `1e2` nie je integer.

Zoznam v query stringu používa PHP zápis so zátvorkami `ids[]=3&ids[]=4` (OpenAPI pomenuje parameter `ids[]`); hodnota oddelená čiarkami používa `Split`. V query môžu byť len skaláry, enumy a zoznamy z nich; vnorené objekty čítajte z tela cez `#[Body]`.

Mapovanie DTO podporuje len päť zdrojov vyššie; vstup z iného zdroja použije `params()` s vlastným `InputParam`.

## Validačné obmedzenia

Atribúty validačných obmedzení sú v `Tomaj\NetteApi\Validation` a dajú sa kombinovať na jednej property. Spúšťajú sa po úspešnej konverzii na skonvertovanú hodnotu; validačné obmedzenie na zozname validuje jeho dĺžku, nie jeho skalárne položky.

| Atribút | Použitie | Význam |
| --- | --- | --- |
| `#[Range(min: 1, max: 100)]` | `int`, `float` | inkluzívne číselné hranice; jednu možno vynechať |
| `#[Length(min: 1, max: 80)]` | `string`, `array` | počet Unicode znakov alebo položiek zoznamu; jednu hranicu možno vynechať; zoznam nad `max` sa odmietne skôr, než sa namapujú jeho položky |
| `#[Pattern('/^[A-Z]+$/')]` | `string` | kompletná zhoda s PCRE (pridá sa modifikátor `D`); neplatný pattern zlyhá pri kompilácii. OpenAPI dostane `pattern`, keď sa regex dá preniesť do JavaScriptu, inak `x-pattern` |
| `#[Email]` | `string` | platná emailová adresa |

Validácia zhromaždí všetky chyby polí, nezastaví sa na prvej. Jedna požiadavka nahlási najviac 100 chýb vrátane vnorených zoznamov; keď niečo ostalo nekontrolované, povie to poznámka pod kľúčom `body`. Hlášky uvádzajú skutočné limity, napríklad `Value must have between 1 and 50 items` namiesto všeobecného `Invalid value`.

### Vlastné validačné obmedzenia

Vlastné validačné obmedzenie implementuje `ConstraintInterface` a zároveň je PHP atribútom:

```php
interface ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string;
}
```

Pri úspechu vráťte `null`, pri chybe jednu hlášku. `ConstraintContext` sprístupňuje cestu property, triedu DTO a `value('sibling')` pre ostatné skonvertované properties toho istého DTO. Pre neplatný vstup klienta nevyhadzujte výnimku. Vlastné validačné obmedzenia nemajú reprezentáciu v OpenAPI, pokiaľ aplikácia nerozšíri OpenAPI buildery.

### Validačné obmedzenia položiek zoznamu

Obmedzenie na zozname validuje zoznam, takže pre `list<int>` alebo `list<string>` neexistujú validačné obmedzenia jednotlivých položiek. Ak chcete skontrolovať každú položku, napíšte vlastné validačné obmedzenie, ktoré prejde zoznam v cykle, alebo použite zoznam malých DTO položiek, ktorý validuje každú položku a hlási ju ako `items[1].quantity`.

## Vlastné konverzie cez Cast

`#[Cast]` použite, keď má pole aplikačne špecifickú textovú reprezentáciu. Pomenuje triedu castera a voliteľne odovzdá argumenty konštruktora:

```php
interface CasterInterface
{
    public function cast(mixed $value): mixed;
}

final class CastException extends RuntimeException
{
}
```

Castery sú v `Tomaj\NetteApi\Input` vrátane `DateFormat`, `UnixTimestamp` a `Split`. Caster beží **namiesto** vstavanej konverzie pre danú property. Spustí sa ako prvý, vráti deklarovanú hodnotu property a až potom sa na skonvertovanej hodnote spustia `Range`, `Length`, `Pattern`, `Email` a vlastné validačné obmedzenia.

Caster hlási neplatný klientsky vstup vyhodením `Tomaj\NetteApi\Input\CastException` so zrozumiteľnou hláškou. Zmení sa na 400 v bežnej chybovej obálke pod cestou property:

```json
{
  "status": "error",
  "message": "wrong input",
  "detail": {
    "issuedOn": ["Date must match d.m.Y, for example 24.12.2026"]
  }
}
```

Každá iná výnimka z castera alebo akákoľvek výnimka z konštruktora vnoreného DTO je chyba servera, nie validačná chyba: error handler na ňu po autorizácii odpovie `500`. Kompilácia kontajnera overí, že caster implementuje `CasterInterface`, akceptuje argumenty atribútu a deklaruje natívny návratový typ `cast()` zhodný s typom property (pre `list<T>` `array` s PHPDoc zoznamu; `mixed` sa neakceptuje).

### Nápovedy OpenAPI schémy

Caster môže voliteľne implementovať `OpenApiCasterInterface` popri `CasterInterface`:

```php
interface OpenApiCasterInterface
{
    /** @return array{type?: string, format?: string, example?: mixed} */
    public function openApiSchema(): array;
}
```

Vrátené `type`, `format` a `example` popisujú **hodnotu prenášanú v požiadavke**, nie výsledný PHP objekt. Bez tohto voliteľného rozhrania OpenAPI použije reťazcovú schému a formát môže popísať description/example DTO. Caster nikdy nemení runtime validáciu cez svoju nápovedu schémy.

### Vstavané castery a český dátum

`DateFormat`, `UnixTimestamp` a `Split` pokrývajú bežné neštandardné reprezentácie:

| Caster | Príklad vstupu | Výsledok |
| --- | --- | --- |
| `#[Cast(DateFormat::class, 'd.m.Y')]` | `'24.12.2026'` | `DateTimeImmutable` o polnoci, odmietne nemožné dátumy a nadbytočné znaky na konci |
| `#[Cast(UnixTimestamp::class)]` | `1798185600` alebo `'1798185600'` | `DateTimeImmutable` zo sekúnd od Unix epoch |
| `#[Cast(Split::class, ',')]` | `'red,green,blue'` | `list<string>` (vždy reťazce: property `list<int>` potrebuje vlastný caster); bez implicitného trimovania alebo odstraňovania prázdnych položiek |

```php
final readonly class IssueInput
{
    public function __construct(
        #[Body]
        #[Cast(DateFormat::class, 'd.m.Y')]
        public DateTimeImmutable $issuedOn,
    ) {
    }
}
```

Nápoveda schémy `DateFormat('d.m.Y')` je `{type: string, example: '24.12.2026'}`; len `DateFormat('Y-m-d')` pridá `format: date`.

### CSV na list&lt;string&gt;

```php
final readonly class ReportInput
{
    /** @param list<string> $columns */
    public function __construct(
        #[Query]
        #[Cast(Split::class, ',')]
        public array $columns = [],
    ) {
    }
}
```

V OpenAPI je property jeden reťazec a konzola ukazuje jedno textové pole. `?columns=id,createdAt,total` sa zmení na `['id', 'createdAt', 'total']`; prázdny reťazec dá `['']`, čo môže validačné obmedzenie zamietnuť.

### Desatinný reťazec na value object Money

Vlastná trieda castera môže vytvoriť value object aplikácie. Nasledujúci príklad zámerne spracuje desatinnú čiarku bez konverzie na float:

```php
final readonly class DecimalMoneyCaster implements CasterInterface, OpenApiCasterInterface
{
    public function cast(mixed $value): Money
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*),([0-9]{2})$/D', $value, $m)) {
            throw new CastException('Money must use a comma and exactly two decimal places, for example 12,50');
        }

        return Money::fromMinorUnits(((int) $m[1]) * 100 + (int) $m[2], 'EUR');
    }

    public function openApiSchema(): array
    {
        return ['type' => 'string', 'example' => '12,50'];
    }
}

final readonly class PriceInput
{
    public function __construct(
        #[Body]
        #[Cast(DecimalMoneyCaster::class)]
        public Money $price,
    ) {
    }
}
```

`12,50` sa stane `Money` a validačné obmedzenia dostanú tento objekt. `12.50`, `1a` a pole vytvoria 400 na `price` s hláškou castera. Keďže caster nahrádza vstavanú konverziu, `Money` môže mať privátny konštruktor a statickú továrenskú metódu.

