---
title: 'Typovaný vstup: konverzia, castery a validačné obmedzenia'
description: 'Ako typované DTO v Nette API striktne konvertuje hodnoty, spúšťa vlastné castery a validuje cez Range, Length, Pattern, Email a vlastné validačné obmedzenia.'
---

# Typovaný vstup: konverzia, castery a validačné obmedzenia

Táto stránka popisuje, čo sa stane s hodnotou medzi požiadavkou a property DTO: striktná konverzia, `#[Cast]` a atribúty validačných obmedzení. Základy sú v stránke [Typovaný vstup (DTO)](/sk/guide/typed-input).

## Striktná konverzia

Typovaný vstup sa nespolieha na voľné PHP pretypovania skalárov.

| Cieľový typ | Akceptovaný vstup |
| --- | --- |
| `string` | reťazec |
| `int` | celé číslo alebo reťazec len s voliteľným znamienkom a číslicami, v rozsahu PHP integer |
| `float` | integer/float alebo kompletný desatinný či vedecký číselný reťazec; NaN a nekonečno sa odmietnu |
| `bool` | boolean `true`/`false`, integer `1`/`0`, reťazec `'true'`/`'false'`/`'1'`/`'0'` (bez ohľadu na veľkosť písmen) |
| backed enum | presne jedna z jeho backing hodnôt; enumy s integer hodnotami používajú pravidlá konverzie integer |
| `DateTimeImmutable` | RFC 3339 reťazec s dátumom, časom a explicitným offsetom, napríklad `2026-11-06T14:30:00+01:00` (`T` a `Z` môžu byť malé písmená; priestupná sekunda ako `23:59:60` sa odmietne, pretože ju PHP nevie vyjadriť) |
| `array` | zoznam, ktorého každá položka zodpovedá typu z PHPDoc |

`?page=1a` pre integer je `400`, nikdy `1` alebo `null`. Biele znaky sa implicitne neodstránia a neznáme reťazce sa nemenia na `false`. JSON čísla a booleany si zachovajú typy, takže celé číslo zapísané ako float, `1.0` alebo `1e2`, nie je integer: klient, ktorý celé čísla serializuje ako floaty, musí pre integer property poslať `1`. Normalizácia aplikácie patrí do handlera alebo vlastného validačného obmedzenia, nie do prekvapivých globálnych pretypovaní.

Zoznam v query stringu používa PHP zápis so zátvorkami `ids[]=3&ids[]=4` a OpenAPI pomenuje parameter `ids[]`, aby ho vygenerovaní klienti posielali v tomto tvare. Hodnota oddelená čiarkami namiesto toho používa `Split`. Formulárové telá podporujú PHP názvy so zátvorkami; iné vnorené formulárové kódovania sa nedekódujú. V query môžu byť len skaláre, enumy a zoznamy z nich: vnorené DTO alebo `list<ItemInput>` za `#[Query]` je chybou kompilácie, pretože OpenAPI nevie popísať vnorenie v PHP zápise so zátvorkami pre vygenerovaného klienta. Vnorené objekty čítajte z tela cez `#[Body]`.

Mapovanie DTO podporuje len päť zdrojov vyššie; vstup z iného zdroja použije `params()` s vlastným `InputParam`.

## Validačné obmedzenia

Atribúty validačných obmedzení sú v `Tomaj\NetteApi\Validation` a dajú sa kombinovať na jednej property. Spúšťajú sa po úspešnej konverzii na skonvertovanú hodnotu; validačné obmedzenie na zozname validuje jeho dĺžku, nie jeho skalárne položky.

| Atribút | Použitie | Význam |
| --- | --- | --- |
| `#[Range(min: 1, max: 100)]` | `int`, `float` | inkluzívne číselné hranice; jednu možno vynechať |
| `#[Length(min: 1, max: 80)]` | `string`, `array` | počet Unicode znakov alebo položiek zoznamu; jednu hranicu možno vynechať; zoznam nad `max` sa odmietne skôr, než sa namapujú jeho položky |
| `#[Pattern('/^[A-Z]+$/')]` | `string` | kompletná zhoda s PCRE, `$` sedí len na úplnom konci, pretože sa pridáva modifikátor `D` (`/^[0-9]{5}$/` odmietne `81101` s koncovým novým riadkom); neplatný pattern je chybou kompilácie; OpenAPI dostane `pattern` len bez iných príznakov než `u` a `D`, pretože príznaky ako `i` sa tam nedajú vyjadriť; syntax, ktorú pozná len PCRE (`\A`, `\z`, `\e`, `\g`, `\x{...}`, posesívne kvantifikátory, atomické skupiny, komentáre, reset vetiev, rekurzia, `(?P<name>...)`, inline flagy, POSIX triedy ako `[[:alpha:]]`), tiež vynechá `pattern`; `\p{L}` a kvantifikátor ako `{2,5}` sú v poriadku |
| `#[Email]` | `string` | platná emailová adresa |

Validácia zhromaždí všetky chyby polí, nezastaví sa na prvej; jedna požiadavka nahlási najviac prvých 100 chýb spolu, aj z vnorených zoznamov, zvyšok tela sa nekontroluje a potom príde poznámka `Only the first 100 errors are reported, the rest of the request was not checked` pod kľúčom `body`, len ak sa skutočne niečo nechalo nekontrolované. Hlášky uvádzajú skutočné limity, napríklad `Value must have between 1 and 50 items` namiesto všeobecného `Invalid value`.

### Vlastné validačné obmedzenia

Vlastné validačné obmedzenie implementuje `ConstraintInterface` a zároveň je PHP atribútom:

```php
interface ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string;
}
```

Pri úspechu vráti `null`, pri chybe jednu hlášku zrozumiteľnú vývojárovi. `ConstraintContext` sprístupňuje cestu property, triedu DTO a `value('sibling')` pre ostatné skonvertované properties toho istého DTO; predvolené hodnoty sa kontrolujú pri kompilácii kontajnera spolu so všetkými predvolenými hodnotami súrodencov a súrodenec bez predvolenej hodnoty je tam `null`; validačné obmedzenia nesmú hádzať výnimky pre bežný neplatný vstup klienta. Vlastné validačné obmedzenia nemajú reprezentáciu v OpenAPI, pokiaľ aplikácia nerozšíri OpenAPI buildery.

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

Castery sú v `Tomaj\NetteApi\Input` vrátane `DateFormat`, `UnixTimestamp` a `Split`. Caster beží **namiesto** vstavanej konverzie pre danú property. Spustí sa ako prvý, vráti deklarovanú hodnotu property a až potom sa na skonvertovanej hodnote spustia `Range`, `Length`, `Pattern`, `Email` a vlastné validačné obmedzenia. Vnorené cesty fungujú presne ako inde.

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

Iné výnimky sú chyby programátora/servera a nemaskujú sa ako klientske validačné chyby. Kompilácia kontajnera overí, že trieda castera existuje, je inštanciovateľná, implementuje `CasterInterface`, akceptuje deklarované argumenty atribútu a má natívny návratový typ `cast()` zhodný s typom property (alebo zdokumentovaný kovariantný typ podtriedy). Caster pre `list<T>` musí deklarovať `array` so zhodným PHPDoc položiek zoznamu. Kontrola je obmedzená na natívne deklarácie; `mixed` nie je akceptovaný ako návratový typ castera. Nekompatibilný caster zlyhá pri kompilácii, nie pri prvej požiadavke.

### Nápovedy OpenAPI schémy

Caster môže voliteľne implementovať `OpenApiCasterInterface` popri `CasterInterface`:

```php
interface OpenApiCasterInterface
{
    /** @return array{type?: string, format?: string, example?: mixed} */
    public function openApiSchema(): array;
}
```

Vrátené `type`, `format` a `example` popisujú **hodnotu na drôte**, nie výsledný PHP objekt. Bez tohto voliteľného rozhrania OpenAPI použije reťazcovú schému a formát môže popísať description/example DTO. Caster nikdy nemení runtime validáciu cez svoju nápovedu schémy.

### Vstavané castery a český dátum

`DateFormat`, `UnixTimestamp` a `Split` pokrývajú bežné neštandardné reprezentácie:

| Caster | Príklad vstupu | Výsledok |
| --- | --- | --- |
| `#[Cast(DateFormat::class, 'd.m.Y')]` | `'24.12.2026'` | `DateTimeImmutable` o polnoci, odmietne nemožné dátumy a vstup s koncovkou |
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

Nápoveď schémy `DateFormat('d.m.Y')` je `{type: string, example: '24.12.2026'}`. Len `DateFormat('Y-m-d')` pridá `format: date`, pretože OpenAPI `date` znamená plný dátum podľa RFC 3339. NUL bajt alebo iná nespracovateľná hodnota je `400` na danom poli, nie výnimka.

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

V OpenAPI je property jeden reťazec s príkladom `red,green,blue` a konzola ukazuje jedno textové pole. `?columns=id,createdAt,total` sa zmení na `['id', 'createdAt', 'total']`. `Split` vráti prázdny zoznam pre vynechanú voliteľnú property len cez jej bežnú predvolenú hodnotu; explicitne prítomný prázdny reťazec vytvorí `['']`, čo môže validačné obmedzenie zamietnuť.

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

`12,50` sa stane `Money` a následné vlastné validačné obmedzenie `PositiveMoney` dostane tento objekt `Money`. `12.50`, `1a` a pole vytvoria 400 na `price` s hláškou castera. Formátovanie špecifické pre menu ostáva v casteri aplikácie namiesto ďalšieho globálneho skalárneho pravidla. Keďže caster nahrádza vstavanú konverziu, `Money` sa nikdy nekompiluje ako transportné DTO: môže mať privátny konštruktor a statickú továrenskú metódu a kompilátor len skontroluje, že `cast()` deklaruje návratový typ `Money`. Pomenované argumenty castera, napríklad `#[Cast(MyCaster::class, suffix: 'x')]`, si v skompilovanom kontajneri zachovajú názvy.

