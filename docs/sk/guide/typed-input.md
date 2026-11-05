---
title: Typovaný vstup (DTO)
description: 'Typované DTO požiadavky v Nette API: atribúty Query, Path, Header, Body a File, striktná konverzia, vnorená validácia, OpenAPI a migrácia z params().'
---

# Typovaný vstup (DTO)

Triedy na tejto stránke sú súčasťou Nette API 4.0. Spustiteľný projekt je v [`examples/typed-input`](/sk/examples/typed-input).

Typované vstupné DTO drží kontrakt požiadavky pri hodnotách, ktoré handler používa. Atribúty určujú, odkiaľ hodnota pochádza, typy properties určujú jej PHP typ a atribúty obmedzení validáciu. Rovnaká deklarácia napája validáciu, OpenAPI aj API konzolu.

`TypedHandler` prevedie deklaráciu DTO na existujúce objekty `InputParam`. `BaseHandler::params()` a `handle(array $params)` ostávajú plne podporované.

## Najmenší handler

Dedeňte z `TypedHandler` a v `handleDto()` deklarujte práve jeden nenulovateľný parameter DTO. Trieda parametra `handleDto()` je vstupné DTO; netreba ju opakovať v atribúte ani konfigurácii.

```php
namespace App\Api;

use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\Range;

final readonly class ListUsersInput
{
    public function __construct(
        #[Query(description: 'Page number')]
        #[Range(min: 1)]
        public int $page = 1,

        #[Query]
        public ?UserStatus $status = null,
    ) {
    }
}

final class ListUsersHandler extends TypedHandler
{
    public function handleDto(ListUsersInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'page' => $input->page,
            'status' => $input->status?->value,
            'users' => [],
        ]);
    }
}
```

`GET /api/v1/users/list?page=2&status=active` vytvorí `ListUsersInput` s celočíselnou hodnotou `page` a enumom `UserStatus`. `TypedHandler` implementuje `handle(array $params)` ako final; aplikačný kód píše len `handleDto()`.

Závislosti v konštruktore, `summary()`, `description()`, `outputs()`, tagy a registrácia fungujú ako pri `BaseHandler`. Atribúty endpointu a autorizácie sú nezávislé od vstupných atribútov a dajú sa kombinovať.

## Atribúty zdroja

Atribúty sú v `Tomaj\NetteApi\Input` a cielia na promované properties konštruktora. Každá promovaná property má práve jeden atribút zdroja. Externý názov je predvolene názov property; odovzdaním názvu ho zmeníte. `description` a opakovateľné `examples` sa stanú popisom a príkladmi vygenerovaného `InputParam`.

| Atribút | Zdroj | Vygenerovaný parameter |
| --- | --- | --- |
| `#[Query]` | query string | `GetInputParam` |
| `#[Path]` | nájdená cesta endpointu | `PathInputParam` |
| `#[Header]` | HTTP hlavička | `HeaderInputParam` |
| `#[Body]` | pole JSON, formulárového alebo multipart tela | `BodyInputParam` |
| `#[File]` | nahraný súbor | `FileInputParam` |

Napríklad `#[Header('X-Request-Id')] public ?string $requestId = null` číta `X-Request-Id`, nie `requestId`. Vyhľadávanie hlavičky nerozlišuje veľkosť písmen.

`PathInputParam` a `HeaderInputParam` sú verejné aj pre handlery, ktoré priamo používajú `params()`. `ParamType` získa `Path` a `Header`; `ParamsProcessor` načíta oba zdroje a OpenAPI ich zapíše ako `in: path` a `in: header`.

```php
public function params(): array
{
    return [
        (new PathInputParam('id', InputType::INTEGER))->setRequired(),
        new HeaderInputParam('X-Request-Id', InputType::STRING),
    ];
}
```

Parameter cesty sa deklaruje v URL endpointu ako `{name}`, napríklad `users/{id}/detail`. Je povinný a placeholder v OpenAPI ceste má rovnaký názov. Chýbajúci alebo nedeklarovaný parameter cesty je chybou kompilácie kontajnera. Šablóny URL endpointov sú malá routingová zmena potrebná pre tento návrh; statické URL endpointov fungujú bez zmeny.

### Povinné hodnoty a predvolené hodnoty

Nenulovateľná property bez predvolenej hodnoty je povinná. Nulovateľná property alebo property s predvolenou hodnotou je voliteľná. Chýbajúca nulovateľná hodnota sa stane `null`; chýbajúca hodnota s predvolenou hodnotou použije túto hodnotu. Rovnako sa nastaví `required` v OpenAPI.

Povinné parametre cesty nemôžu byť nulovateľné ani mať predvolenú hodnotu. Voliteľné pole tela akceptuje vynechanie poľa, zatiaľ čo explicitné JSON `null` akceptuje len nulovateľná property. Prázdny reťazec sa potichu nemení na `null`.

## Kompletné príklady požiadaviek

### GET s query, cestou a hlavičkou

```php
final readonly class UserDetailInput
{
    public function __construct(
        #[Path]
        #[Range(min: 1)]
        public int $id,

        #[Query]
        #[Range(min: 1, max: 100)]
        public int $limit = 20,

        #[Query]
        public ?UserStatus $status = null,

        #[Header('X-Request-Id')]
        public ?string $requestId = null,
    ) {
    }
}
```

Požiadavka:

```http
GET /api/v1/users/42/detail?limit=10&status=active HTTP/1.1
X-Request-Id: req-01J8Y7N6R9
```

Handler dostane `id: 42`, `limit: 10`, `status: UserStatus::Active` a reťazec s ID požiadavky. Nie sú potrebné pretypovania ani prístupy ku kľúčom poľa.

### POST s vnoreným DTO a poľom

```php
final readonly class AddressInput
{
    public function __construct(
        #[Body]
        #[Length(min: 1, max: 120)]
        public string $street,

        #[Body]
        #[Pattern('/^[0-9]{5}$/')]
        public string $postalCode,
    ) {
    }
}

final readonly class ItemInput
{
    public function __construct(
        #[Body]
        public string $sku,

        #[Body]
        #[Range(min: 1)]
        public int $quantity,
    ) {
    }
}

final readonly class CreateOrderInput
{
    /** @param list<ItemInput> $items */
    public function __construct(
        #[Body]
        public string $customerEmail,

        #[Body]
        public AddressInput $address,

        #[Body]
        #[Length(min: 1, max: 50)]
        public array $items,

        #[Body]
        public ?DateTimeImmutable $deliveryAt = null,
    ) {
    }
}
```

JSON telo požiadavky:

```json
{
  "customerEmail": "ada@example.com",
  "address": {"street": "Main 1", "postalCode": "81101"},
  "items": [
    {"sku": "BOOK-1", "quantity": 2},
    {"sku": "PEN-2", "quantity": 1}
  ],
  "deliveryAt": "2026-11-06T14:30:00+01:00"
}
```

Mapper DTO rekurzívne vytvorí `AddressInput` a oba objekty `ItemInput`. `list<ItemInput>` je povinný PHPDoc pre pole DTO; pre polia skalárov sú podporované `list<string>`, `list<int>`, `list<float>` a `list<bool>`. Asociatívne polia, union typy okrem nulovateľných, `mixed`, `object` a netypované polia sa odmietnu pri kompilácii kontajnera, nie v polovici požiadavky.

Rovnaké DTO funguje s formulárovými a multipart poľami. Vnorené polia používajú PHP zápis s hranatými zátvorkami, napríklad `address[street]` a `items[0][sku]`; `deliveryAt` ostáva rovnaký RFC 3339 reťazec. Parsovanie tela závisí od content typu, nie od JSON schémy.

## Content typy tela

`TypedHandler` s poľami tela predvolene akceptuje **`application/json`** aj **`application/x-www-form-urlencoded`**. Ak jeho DTO obsahuje property `#[File]`, akceptuje aj **`multipart/form-data`**, aby bolo možné poslať súbory spolu s poľami tela. Content type sa porovnáva bez parametrov, takže `application/json; charset=utf-8` je akceptovaný.

OpenAPI ponúka rovnaké možnosti v `requestBody.content`: JSON a URL-enkódovaný formulár alebo JSON a multipart pri súbore. `BodyInputParam` reprezentuje spoločné polia a `FileInputParam` zachováva existujúce správanie uploadov; stále existuje len jedna cesta validácie a mapovania.

### Obmedzenie akceptovaných typov cez Consumes

Ak endpoint zámerne podporuje len jednu reprezentáciu, použite atribút triedy `#[Consumes]`. Akceptuje jeden alebo viac media typov:

```php
#[Consumes('application/json')]
final class CreateOrderHandler extends TypedHandler
{
    public function handleDto(CreateOrderInput $input): ResponseInterface
    {
        // Len JSON
    }
}
```

```php
#[Consumes('application/json', 'application/x-www-form-urlencoded')]
final class ImportHandler extends TypedHandler
{
    // Explicitne rovnaké dva typy ako predvolené pre DTO bez súborov.
}
```

Multipart-only upload endpoint použije `#[Consumes('multipart/form-data')]`. Obmedzenie na formulárový typ bez `multipart/form-data` znamená, že endpoint nemôže prijať súbory; nemožná kombinácia, napríklad povinný `#[File]` s konzumáciou len JSON, je chybou kompilácie kontajnera.

Požiadavka s iným content typom sa pred mapovaním odmietne s **415 Unsupported Media Type**. Pri zapnutých detailoch chýb hláška uvádza prijatý typ aj presné akceptované typy, napríklad:

```json
{
  "status": "error",
  "message": "unsupported media type",
  "detail": "Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded."
}
```

Chýbajúci content type tela pri požiadavke, ktorá telo vyžaduje, dostane rovnakú nápomocnú 415 namiesto neskoršej chyby null property. Pri zapnutých Problem Details sa použije status 415 a rovnaká hláška v tomto formáte.

### Rozdiely medzi JSON, formulárom a multipart

| Problém | JSON | URL-enkódovaný formulár / multipart |
| --- | --- | --- |
| Vnorené DTO | objekty: `{"address":{"street":"Main 1"}}` | názvy so zátvorkami: `address[street]=Main+1` |
| Zoznamy DTO | polia: `"items":[{"sku":"BOOK-1"}]` | indexované názvy: `items[0][sku]=BOOK-1` |
| Natívne skalárne typy | čísla a booleany ostávajú JSON číslami/booleanmi | hodnoty prichádzajú ako reťazce a prechádzajú rovnakou striktnou konverziou |
| Null | explicitné `null` je povolené len pre nulovateľnú property | neexistuje null literál: vynechajte pole, aby použilo null alebo predvolenú hodnotu |
| Prázdny reťazec | skutočný reťazec, nie null | skutočný reťazec, nie null |
| Objekt vs zoznam | `{}` je objekt, aj keď mu chýbajú properties. `[]` je zoznam a je zamietnutý všade, kde sa vyžaduje objekt, vrátane prázdneho zoznamu. JSON objekt je zamietnutý tam, kde sa vyžaduje zoznam | názvy so zátvorkami tvoria objekt (`address[street]`); indexované názvy tvoria zoznam (`items[0]`) |
| Súbory | nepodporované | len `multipart/form-data` |

Napríklad JSON `"quantity": 2` ostane integer, zatiaľ čo formulárové `quantity=2` sa stane integer až po tom, ako kompletný reťazec prejde striktnou konverziou. Ani jedno neakceptuje `2pcs`. JSON `false` a formulárové `remember=0` sú platné booleany; ľubovoľný neprázdny formulárový reťazec sa potichu nestane `true`.

Pre voliteľnú formulárovú hodnotu vynechajte `deliveryAt` namiesto poslania `deliveryAt=null` alebo prázdnej hodnoty. Multipart súbor nemožno v tomto návrhu nahradiť JSON base64 reťazcom; aplikácia, ktorá potrebuje base64 uploady, môže ponechať `BaseHandler` a spracovať vlastné raw telo.

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

Castery sú v `Tomaj\NetteApi\Input` vrátane `DateFormat`, `UnixTimestamp` a `Split`. Caster beží **namiesto** vstavannej konverzie pre danú property. Beží prvý, vráti deklarovanú hodnotu property a až potom sa na skonvertovanej hodnote spustia `Range`, `Length`, `Pattern`, `Email` a vlastné obmedzenia. Vnorené cesty fungujú presne ako inde.

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

Iné výnimky sú chyby programátora/servera a nemaskujú sa ako klientske validačné chyby. Kompilácia kontajnera overí, že trieda castera existuje, je inštanciovateľná, implementuje `CasterInterface`, akceptuje deklarované argumenty atribútu a má natívny návratový typ `cast()` zhodný s typom property (alebo zdokumentovaný kovariantný podtriedny typ). Caster pre `list<T>` musí deklarovať `array` so zhodným PHPDoc položiek zoznamu. Kontrola je obmedzená na natívne deklarácie; `mixed` nie je akceptovaný ako návratový typ castera. Nekompatibilný caster zlyhá pri kompilácii, nie pri prvej požiadavke.

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
| `#[Cast(UnixTimestamp::class)]` | `'1798185600'` | `DateTimeImmutable` zo sekúnd od Unix epoch |
| `#[Cast(Split::class, ',')]` | `'red,green,blue'` | `list<string>`; bez implicitného trimovania alebo odstraňovania prázdnych položiek |

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

Nápoveda schémy `DateFormat` je `{type: string, format: date, example: '24.12.2026'}`; `format` je nápoveda, nie tvrdenie, že hodnota na drôte je RFC 3339.

### CSV na list&lt;string&gt;

```php
final readonly class ReportInput
{
    /** @var list<string> */
    #[Query]
    #[Cast(Split::class, ',')]
    public array $columns;
}
```

`?columns=id,createdAt,total` sa zmení na `['id', 'createdAt', 'total']`. `Split` vráti prázdny zoznam pre vynechanú voliteľnú property len cez jej bežnú predvolenú hodnotu; explicitne prítomný prázdny reťazec vytvorí `['']`, čo môže obmedzenie zamietnuť.

### Desatinný reťazec na value object Money

Vlastná trieda castera môže vytvoriť value object aplikácie: Nasledujúci príklad zámerne spracuje desatinnú čiarku bez konverzie na float:

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

`12,50` sa stane `Money` a následné vlastné obmedzenie `PositiveMoney` dostane tento objekt `Money`. `12.50`, `1a` a pole vytvoria 400 na `price` s hláškou castera. Formátovanie špecifické pre menu ostáva v casteri aplikácie namiesto ďalšieho globálneho skalárneho pravidla.

### Nahrávanie súboru

```php
final readonly class AvatarInput
{
    public function __construct(
        #[Body]
        #[Length(min: 1, max: 80)]
        public string $displayName,

        #[File(description: 'PNG or JPEG image')]
        public FileUpload $avatar,
    ) {
    }
}
```

`FileUpload` je typovaný value object knižnice pre nahraný súbor. Sprístupňuje pôvodný názov, content type od klienta, dočasnú cestu, veľkosť a chybu nahrávania; neverí content typu od klienta ani automaticky nepresúva súbor. `?FileUpload` znamená voliteľný súbor a `list<FileUpload>` prijme viac súborov v jednom poli. Pred uložením skontrolujte chybu nahrávania, veľkosť a skutočný typ, rovnako ako pri `FileInputParam`.

## Striktná konverzia

Typovaný vstup sa nespolieha na voľné PHP pretypovania skalárov.

| Cieľový typ | Akceptovaný vstup |
| --- | --- |
| `string` | reťazec |
| `int` | celé číslo alebo reťazec len s voliteľným znamienkom a číslicami, v rozsahu PHP integer |
| `float` | integer/float alebo kompletný desatinný či vedecký číselný reťazec; NaN a nekonečno sa odmietnu |
| `bool` | boolean `true`/`false`, integer `1`/`0`, reťazec `'true'`/`'false'`/`'1'`/`'0'` (bez ohľadu na veľkosť písmen) |
| backed enum | presne jedna z jeho backing hodnôt; enumy s integer hodnotami používajú pravidlá konverzie integer |
| `DateTimeImmutable` | RFC 3339 reťazec s dátumom, časom a explicitným offsetom, napríklad `2026-11-06T14:30:00+01:00` |
| `array` | zoznam, ktorého každá položka zodpovedá typu z PHPDoc |

`?page=1a` pre integer je `400`, nikdy `1` alebo `null`. Biele znaky sa implicitne neodstránia a neznáme reťazce sa nemenia na `false`. JSON čísla a booleany si zachovajú typy. Normalizácia aplikácie patrí do handlera alebo vlastného obmedzenia, nie do prekvapivých globálnych pretypovaní.

Vlastný zdroj môže naďalej použiť `BaseHandler` a `params()`; mapovanie DTO podporuje len päť zdrojov vyššie.

## Obmedzenia

Atribúty obmedzení sú v `Tomaj\NetteApi\Validation` a dajú sa kombinovať na jednej property. Spúšťajú sa po úspešnej konverzii na skonvertovanú hodnotu; obmedzenie na zozname validuje jeho dĺžku, nie jeho skalárne položky.

| Atribút | Použitie | Význam |
| --- | --- | --- |
| `#[Range(min: 1, max: 100)]` | `int`, `float` | inkluzívne číselné hranice; jednu možno vynechať |
| `#[Length(min: 1, max: 80)]` | `string`, `array` | počet Unicode znakov alebo položiek zoznamu; jednu hranicu možno vynechať |
| `#[Pattern('/^[A-Z]+$/')]` | `string` | kompletná zhoda s PCRE; neplatný pattern je chybou kompilácie |
| `#[Email]` | `string` | platná emailová adresa |

Validácia zhromaždí všetky chyby polí, nezastaví sa na prvej. Hlášky pomenujú zamietnuté obmedzenie a jeho skutočné limity, napríklad `Length must be between 1 and 50 items` namiesto všeobecného `Invalid value`.

### Vlastné obmedzenia

Vlastné obmedzenie implementuje `ConstraintInterface` a zároveň je PHP atribútom:

```php
interface ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string;
}
```

Pri úspechu vráti `null`, pri chybe jednu hlášku zrozumiteľnú vývojárovi. `ConstraintContext` sprístupňuje cestu property a triedu DTO; obmedzenia nesmú hádzať výnimky pre bežný neplatný vstup klienta. Vlastné obmedzenia nemajú reprezentáciu v OpenAPI, pokiaľ aplikácia nerozšíri OpenAPI buildery.

## Chybové odpovede

Obálka odpovede ostáva rovnaká ako pri `ParamsProcessor`. Kľúč detailu je cesta poľa: bodky vstupujú do vnorených DTO a hranaté zátvorky označujú položky zoznamu. Najvyššie zdroje používajú svoje externé názvy.

```json
{
  "status": "error",
  "message": "wrong input",
  "detail": {
    "customerEmail": ["Email must be a valid email address"],
    "address.postalCode": ["Pattern must match /^[0-9]{5}$/"],
    "items[1].quantity": ["Range must be greater than or equal to 1"],
    "deliveryAt": ["DateTime must be a valid RFC 3339 date and time"]
  }
}
```

Pri vypnutých detailoch chýb sa vrátia len `status` a `message`. So zapnutými Problem Details sa rovnaké cesty polí a hlášky objavia v jeho rozšírení pre chyby vstupu. Pokazené JSON a nečitateľné telo zachovajú existujúce chyby parsera tela.

Chýbajúci vnorený objekt nahlási cestu vnoreného objektu. Neznáme vstupné polia sa ignorujú, rovnako ako pri bežných query/formulárových parametroch; potichu sa nemapujú na properties.

## OpenAPI a API konzola

`TypedHandler::params()` sprístupní vygenerované objekty `InputParam`, takže existujúca konzola automaticky získa popisky, označenie povinnosti, predvolené hodnoty, príklady, voľby enumov a polia pre súbory. Neexistuje osobitný DTO režim konzoly.

V OpenAPI sa query, path a header properties stanú parametrami operácie. Properties tela sa spoja do jednej objektovej schémy; vnorené DTO a zoznamy sa stanú vnorenými schémami. Predvolene `requestBody.content` obsahuje `application/json` aj `application/x-www-form-urlencoded`, alebo JSON a `multipart/form-data`, ak DTO obsahuje súbor. `#[Consumes]` obmedzí tento zoznam aj akceptované požiadavky na explicitné media typy.

Príklad GET vyššie pridá tento fragment operácie (odpovede a nesúvisiace polia sú vynechané):

```openapi
parameters:
  - name: id
    in: path
    required: true
    schema: {type: integer, minimum: 1}
  - name: limit
    in: query
    required: false
    schema: {type: integer, minimum: 1, maximum: 100, default: 20}
  - name: status
    in: query
    required: false
    schema: {type: string, enum: [active, disabled]}
  - name: X-Request-Id
    in: header
    required: false
    schema: {type: string}
```

Príklad POST pridá:

```openapi
requestBody:
  required: true
  content:
    application/json:
      schema:
        type: object
        required: [customerEmail, address, items]
        properties:
          customerEmail: {type: string, format: email}
          address:
            type: object
            required: [street, postalCode]
            properties:
              street: {type: string, minLength: 1, maxLength: 120}
              postalCode: {type: string, pattern: '^[0-9]{5}$'}
          items:
            type: array
            minItems: 1
            maxItems: 50
            items:
              type: object
              required: [sku, quantity]
              properties:
                sku: {type: string}
                quantity: {type: integer, minimum: 1}
          deliveryAt: {type: string, format: date-time, nullable: true}
```

JSON schéma sa tu generuje len ako výstupný formát OpenAPI. Runtime validácia používa rekurzívny mapper DTO a nikdy nevyžaduje JSON schému.

## Staré a nové handlery fungujú vedľa seba

**Áno: `params()` s `handle(array $params)` aj `TypedHandler` s DTO sú dva podporované spôsoby písania handlerov v jednej aplikácii.** Jeden nevypína druhý. Registrácia, autorizácia, výstupy, obmedzenia požiadaviek, OpenAPI a konzola sú spoločné.

Migrácia je zámerne postupná. Môžete upraviť jeden endpoint, všetky ostatné handlery nechať bez zmeny a ďalší endpoint upraviť, keď sa vám to bude hodiť. Neexistuje globálny prepínač aplikácie ani potreba migrovať všetko naraz.

## Migrácia handlera z params() na DTO

Nasledujúce dvojice ukazujú rovnaký kontrakt požiadavky pred migráciou a po nej. Importy frameworku sú vynechané tam, kde sa nemenia; závislosti aplikácie v konštruktore a vytváranie odpovede ostávajú v handleri.

### 1. Jednoduchý GET: voliteľné/predvolené query parametre a enum

Pred migráciou handler deklaruje každý query parameter, pretypuje hodnoty a mapuje reťazec statusu na enum:

```php
final class ListUsersHandler extends BaseHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('page', InputType::INTEGER))
                ->setDefault(1)
                ->setDescription('Page number'),
            (new GetInputParam('limit', InputType::INTEGER))->setDefault(20),
            (new GetInputParam('status', InputType::STRING))
                ->setAvailableValues(UserStatus::options()),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $page = (int) $params['page'];
        $limit = (int) $params['limit'];
        $status = $params['status'] === null
            ? null
            : UserStatus::from($params['status']);

        if ($page < 1 || $limit < 1 || $limit > 100) {
            return new JsonApiResponse(400, ['status' => 'error']);
        }

        return new JsonApiResponse(200, $this->users->findPage($page, $limit, $status));
    }
}
```

Po migrácii sú predvolené hodnoty, voliteľnosť a povolené hodnoty enumu v DTO. `Range` nahradí ručnú kontrolu hraníc a `handleDto()` dostane hodnoty pripravené na použitie:

```php
final readonly class ListUsersInput
{
    public function __construct(
        #[Query(description: 'Page number')]
        #[Range(min: 1)]
        public int $page = 1,

        #[Query]
        #[Range(min: 1, max: 100)]
        public int $limit = 20,

        #[Query]
        public ?UserStatus $status = null,
    ) {
    }
}

final class ListUsersHandler extends TypedHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function handleDto(ListUsersInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, $this->users->findPage(
            $input->page,
            $input->limit,
            $input->status,
        ));
    }
}
```

Zároveň to ukazuje všeobecné pravidlo pre voliteľné a predvolené hodnoty: `?UserStatus $status = null` je voliteľná hodnota a stane sa `null`; `int $limit = 20` je voliteľná hodnota a pri vynechaní sa stane `20`. Povinná hodnota by bola nenulovateľná a bez predvolenej hodnoty, napríklad `#[Query] public string $search`.

### 2. GET: id v ceste a hlavička požiadavky

Pred migráciou URL endpointu obsahuje `users/{id}/detail` a `params()` deklaruje dve nové verejné triedy parametrov:

```php
final class UserDetailHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PathInputParam('id', InputType::INTEGER))->setRequired(),
            new HeaderInputParam('X-Request-Id', InputType::STRING),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $id = (int) $params['id'];
        $requestId = $params['X-Request-Id'];

        return $this->userDetail($id, $requestId);
    }
}
```

Po migrácii `Path` a `Header` zachovajú externé názvy, zatiaľ čo handler používa výstižné typované properties:

```php
final readonly class UserDetailInput
{
    public function __construct(
        #[Path]
        #[Range(min: 1)]
        public int $id,

        #[Header('X-Request-Id')]
        public ?string $requestId = null,
    ) {
    }
}

final class UserDetailHandler extends TypedHandler
{
    public function handleDto(UserDetailInput $input): ResponseInterface
    {
        return $this->userDetail($input->id, $input->requestId);
    }
}
```

Registrácia endpointu a URL sa nemenia. Deklarácia DTO stále vygeneruje `id` ako povinný parameter cesty a `X-Request-Id` ako voliteľnú hlavičku v OpenAPI.

### 3. POST JSON: vnorený objekt a pole

Pred migráciou `JsonInputParam` obsahuje ručne písanú schému. Handler číta vnorené polia a vytvára vlastné hodnoty:

```php
final class CreateOrderHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new JsonInputParam('body', <<<'JSON'
                {
                  "type": "object",
                  "required": ["customerEmail", "address", "items"],
                  "properties": {
                    "customerEmail": {"type": "string", "format": "email"},
                    "address": {
                      "type": "object",
                      "required": ["street", "postalCode"],
                      "properties": {
                        "street": {"type": "string"},
                        "postalCode": {"type": "string", "pattern": "^[0-9]{5}$"}
                      }
                    },
                    "items": {
                      "type": "array",
                      "minItems": 1,
                      "items": {
                        "type": "object",
                        "required": ["sku", "quantity"],
                        "properties": {
                          "sku": {"type": "string"},
                          "quantity": {"type": "integer", "minimum": 1}
                        }
                      }
                    }
                  }
                }
                JSON))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $body = $params['body'];
        $items = array_map(
            fn (array $item) => new OrderItem($item['sku'], (int) $item['quantity']),
            $body['items'],
        );

        return $this->orders->create(
            $body['customerEmail'],
            new Address($body['address']['street'], $body['address']['postalCode']),
            $items,
        );
    }
}
```

Po migrácii je vnorená štruktúra vyjadrená raz ako DTO triedy. Runtime validácia nepotrebuje JSON schému:

```php
final readonly class AddressInput
{
    public function __construct(
        #[Body] public string $street,
        #[Body] #[Pattern('/^[0-9]{5}$/')] public string $postalCode,
    ) {
    }
}

final readonly class OrderItemInput
{
    public function __construct(
        #[Body] public string $sku,
        #[Body] #[Range(min: 1)] public int $quantity,
    ) {
    }
}

final readonly class CreateOrderInput
{
    /** @param list<OrderItemInput> $items */
    public function __construct(
        #[Body] #[Email] public string $customerEmail,
        #[Body] public AddressInput $address,
        #[Body] #[Length(min: 1)] public array $items,
    ) {
    }
}

final class CreateOrderHandler extends TypedHandler
{
    public function handleDto(CreateOrderInput $input): ResponseInterface
    {
        $items = array_map(
            fn (OrderItemInput $item) => new OrderItem($item->sku, $item->quantity),
            $input->items,
        );

        return $this->orders->create(
            $input->customerEmail,
            new Address($input->address->street, $input->address->postalCode),
            $items,
        );
    }
}
```

`list<OrderItemInput>` je dôležité: mapperu aj OpenAPI hovorí, čo obsahuje položka poľa. Chyba v quantity druhej položky sa nahlási pod `items[1].quantity`.

### 4. Formulárový POST

Pred migráciou dva `PostInputParam` objekty popisujú URL-enkódovaný prihlasovací formulár:

```php
final class LoginHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PostInputParam('email', InputType::STRING))->setRequired(),
            (new PostInputParam('password', InputType::STRING))->setRequired(),
            (new PostInputParam('remember', InputType::BOOLEAN))->setDefault(false),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return $this->sessions->login(
            (string) $params['email'],
            (string) $params['password'],
            (bool) $params['remember'],
        );
    }
}
```

Po migrácii `Body` znamená pole tela bez ohľadu na to, či je požiadavka JSON alebo formulárová:

```php
final readonly class LoginInput
{
    public function __construct(
        #[Body] #[Email] public string $email,
        #[Body] #[Length(min: 12, max: 200)] public string $password,
        #[Body] public bool $remember = false,
    ) {
    }
}

final class LoginHandler extends TypedHandler
{
    public function handleDto(LoginInput $input): ResponseInterface
    {
        return $this->sessions->login($input->email, $input->password, $input->remember);
    }
}
```

Formulár stále posiela `email=ada%40example.com&password=...&remember=1`. Mapper DTO odmietne neznáme boolean reťazce namiesto toho, aby každý neprázdny reťazec považoval za `true`.

### 5. Nahrávanie súboru

Pred migráciou handler dostane surovú položku uploadu a validuje jej chybu nahrávania a veľkosť:

```php
final class AvatarUploadHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PostInputParam('displayName', InputType::STRING))->setRequired(),
            (new FileInputParam('avatar'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $avatar = $params['avatar'];
        if ($avatar['error'] !== UPLOAD_ERR_OK || $avatar['size'] > 100_000) {
            return new JsonApiResponse(400, ['status' => 'error']);
        }

        return $this->avatars->store((string) $params['displayName'], $avatar['tmp_name']);
    }
}
```

Po migrácii `FileUpload` poskytne typovaný prístup k rovnakým metadátam. Aplikačné kontroly skutočného typu súboru a ukladania ostávajú explicitné:

```php
final readonly class AvatarUploadInput
{
    public function __construct(
        #[Body] #[Length(min: 1, max: 80)] public string $displayName,
        #[File] public FileUpload $avatar,
    ) {
    }
}

final class AvatarUploadHandler extends TypedHandler
{
    public function handleDto(AvatarUploadInput $input): ResponseInterface
    {
        $avatar = $input->avatar;
        if ($avatar->error !== UPLOAD_ERR_OK || $avatar->size > 100_000) {
            return new JsonApiResponse(400, ['status' => 'error']);
        }

        return $this->avatars->store($input->displayName, $avatar->temporaryPath);
    }
}
```

Názvy multipart polí ostávajú `displayName` a `avatar`. Migrácia na DTO nenahrádza bezpečnostné kontroly aplikácie ani PHP limity uploadov.

### 6. Vlastná validácia

Pred migráciou validácia závislá od viacerých hodnôt žije v `handle()`:

```php
final class ScheduleHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new JsonInputParam('body', $this->scheduleSchema()))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $startsAt = new DateTimeImmutable($params['body']['startsAt']);
        $endsAt = new DateTimeImmutable($params['body']['endsAt']);
        if ($endsAt <= $startsAt) {
            return new JsonApiResponse(400, [
                'status' => 'error',
                'message' => 'wrong input',
                'detail' => ['endsAt' => ['End must be later than start']],
            ]);
        }

        return $this->schedule->create($startsAt, $endsAt);
    }
}
```

Po migrácii sú jednoduchá konverzia a RFC 3339 parsovanie deklaratívne. Vlastné obmedzenie vlastní pravidlo medzi poľami a zachováva konzistentnú transportnú odpoveď:

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class LaterThan implements ConstraintInterface
{
    public function __construct(private string $otherProperty)
    {
    }

    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        $other = $context->value($this->otherProperty);
        if ($value instanceof DateTimeImmutable && $other instanceof DateTimeImmutable && $value <= $other) {
            return sprintf('Must be later than %s', $this->otherProperty);
        }

        return null;
    }
}

final readonly class ScheduleInput
{
    public function __construct(
        #[Body] public DateTimeImmutable $startsAt,
        #[Body] #[LaterThan('startsAt')] public DateTimeImmutable $endsAt,
    ) {
    }
}

final class ScheduleHandler extends TypedHandler
{
    public function handleDto(ScheduleInput $input): ResponseInterface
    {
        return $this->schedule->create($input->startsAt, $input->endsAt);
    }
}
```

Návrh pridáva `ConstraintContext::value()` na čítanie susedných hodnôt v tom istom DTO, aby obmedzenia medzi properties nemuseli skúmať HTTP požiadavku. Biznis pravidlá, ktoré potrebujú databázu, stále patria do aplikačných služieb; obmedzenie má ostať deterministickou kontrolou kontraktu požiadavky.

### 7. Handler nechajte bez zmeny, ak je jeho správanie zámerné

Nie každý handler treba migrovať. Starší endpoint môže závisieť od pôvodného správania `InputType::INTEGER`, vlastného `InputParam` alebo ručného parsovania nezvyčajného content typu. Ponechanie na `BaseHandler` je platná voľba:

```php
final class LegacyReportHandler extends BaseHandler
{
    public function params(): array
    {
        return [new GetInputParam('offset', InputType::INTEGER)];
    }

    public function handle(array $params): ResponseInterface
    {
        // Existujúce správanie endpointu zámerne ostáva bez zmeny.
        return $this->reports->generate($params['offset']);
    }
}
```

Susediaci `ListReportsHandler` už môže dediť z `TypedHandler`. Tento zmiešaný stav je podporovaný bez časového obmedzenia; migrácia je voľba pohodlia, nie povinnosť zmeniť každý endpoint.

### Kontrolný zoznam migrácie

1. **Zachyťte aktuálny kontrakt:** uložte testy endpointu a aktuálnu OpenAPI operáciu vrátane predvolených hodnôt a chybového správania.
2. **Vytvorte readonly DTO:** jednu promovanú property pre každý query, path, header, body alebo file vstup.
3. **Zachovajte externé názvy:** použite explicitné názvy atribútov ako `#[Header('X-Request-Id')]`, ak sa názov property líši.
4. **Zlaďte povinnosť:** nenulovateľná property bez predvolenej hodnoty je povinná; nulovateľná alebo s predvolenou hodnotou je voliteľná.
5. **Nahraďte ručné deklarácie:** použite backed enumy pre povolené hodnoty a vstavané obmedzenia tam, kde zodpovedajú existujúcim kontrolám.
6. **Spracujte vnorené dáta:** vytvorte vnorené DTO a pridajte PHPDoc `list<ItemInput>` pre polia DTO.
7. **Presuňte len validáciu kontraktu:** databázové vyhľadávania a biznis pravidlá nechajte v službách alebo handleri; pre deterministické pravidlá vstupu použite vlastné obmedzenia.
8. **Prepnite základnú triedu:** nahraďte `handle(array $params)` za `handleDto(Input $input)` a odstráňte ručné pretypovania/prístupy k poľu.
9. **Porovnajte správanie:** spustite úspešné aj chybné požiadavky, skontrolujte cesty polí, porovnajte OpenAPI a skontrolujte formulár konzoly a nahrávanie.
10. **Skontrolujte kompatibilitu:** zámerne prísnejšiu konverziu zdôraznite v changelogu alebo migračných poznámkach. Ak musí ostať staré správanie, endpoint nechajte bez zmeny.

## Príklad

`examples/typed-input` má rovnaký samostatný Composer layout a konvenciou `e2e.sh` v CI ako ostatné príklady. Súbory sú rozložené ako v ostatných príkladoch (`public/index.php`):

```text
examples/typed-input/
├── composer.json
├── README.md
├── e2e.sh
├── config/common.neon
├── public/index.php
└── app/Api/
    ├── ListUsersHandler.php       # query, backed enum a predvolené hodnoty
    ├── UserDetailHandler.php      # path id a hlavička X-Request-Id
    ├── CreateOrderHandler.php     # JSON/formulárové telo, vnorené DTO a list<ItemInput>
    ├── AvatarUploadHandler.php    # multipart telo a FileUpload
    └── Input/                     # readonly DTO a UserStatus
```

End-to-end test pokrýva úspešné JSON, formulárové a multipart požiadavky, predvolené hodnoty, konverziu enumov, každý atribút zdroja, striktné zamietnutie `1a`, cesty vnorených chýb a vygenerovaný OpenAPI dokument. CI ho spúšťa spolu s ostatnými príkladmi.

## Rozhodnutia návrhu

- **Metadáta pri kompilácii.** `NetteApiExtension` prejde DTO reflexiou a zapíše definície `InputParam` a metadáta mapovania do skompilovaného kontajnera. Požiadavky a generovanie OpenAPI neopakujú reflexiu a nepotrebujú nastavovanie invalidácie cache. Zmena DTO prebuduje kontajner rovnako ako zmena atribútov endpointov. Bez DI rozšírenia sa malý register vygenerovaných metadát vytvorí raz za požiadavku; nevzniká runtime závislosť na Nette Cache.
- **Jedna pipeline metadát.** Metadáta DTO sa prekladajú na existujúce objekty `InputParam`, nepridávajú sa osobitné DTO vetvy do validácie, OpenAPI a konzoly.
- **Rekurzívna runtime validácia.** Konzistentne spracuje JSON, formulár a multipart a poskytne presné cesty properties. JSON schéma sa generuje len pre OpenAPI.
- **Len readonly promované properties.** DTO sa vytvorí raz cez konštruktor a mapper ho nemôže čiastočne meniť.
- **Striktná, explicitná konverzia skalárov.** Neplatné klientske dáta sú `400`, nie prekvapivé pretypovanie.
- **Malá rozšíriteľná sada obmedzení.** Range, Length, Pattern a Email pokryjú bežné API kontrakty; vlastné obmedzenia pokryjú pravidlá aplikácie bez závislosti na validačnom frameworku.
- **Predvolene JSON aj formulár, obmedzenia cez `Consumes`.** Jedno DTO obslúži JSON aj HTML/PHP formulárových klientov a OpenAPI ponúka rovnaké media typy; multipart sa povolí pri súbore. Predvolený len JSON by bol jednoduchší, ale odporoval by požiadavke, že telo nemusí byť JSON. Argument endpoint metódy ako `accepts:` bol zamietnutý, pretože by oddelil transportný kontrakt od ostatných atribútov triedy endpointu. Názov `Consumes` bol zvolený pred `Accepts`, ktorý sa ľahko zamieňa s HTTP hlavičkou `Accept` pre vyjednávanie odpovede.
- **`handleDto(Input $input)` ako metóda typovaného handlera.** Je explicitná a vyhýba sa pocitu magickej metódy `__invoke`, pričom reflexia stále číta triedu DTO z jej parametra. `process` bolo zvážené, no nevyjadruje, že ide o vstupný bod handlera; dodatočný atribút `#[Handler]` bol zamietnutý, pretože názov metódy je už jednoznačný a atribút by nepridal žiadnu informáciu.
- **Atribúty `Cast` s `CasterInterface`.** Aplikačné formáty ostávajú lokálne na property, argumenty konštruktora pokryjú voľby ako `d.m.Y` a `CastException` poskytne chyby 400 s cestou poľa. `Convert` a `Process` boli zvážené, no sú menej presné: konverzia môže znamenať ktorúkoľvek fázu mapovania a spracovanie nemusí vytvoriť typovanú hodnotu. Symfony Serializer/normalizéry boli zamietnuté ako väčší druhý systém mapovania objektov. Voliteľné rozhranie nápovedy schémy bolo uprednostnené pred povinnými OpenAPI metadátami v každom casteri.
