---
title: Typovaný vstup (DTO)
description: 'Typované DTO požiadavky v Nette API: atribúty Query, Path, Header, Body a File, povinné hodnoty, chyby, OpenAPI a API konzola. Druhý, rovnocenný spôsob deklarácie vstupu handlera popri params().'
---

# Typovaný vstup (DTO)

Triedy na tejto stránke sú súčasťou Nette API 4.0. Spustiteľný projekt je v [`examples/typed-input`](/sk/examples/typed-input).

Typované vstupné DTO drží kontrakt požiadavky pri hodnotách, ktoré handler používa. Atribúty určujú, odkiaľ hodnota pochádza, typy properties určujú jej PHP typ a atribúty validačných obmedzení validáciu. Rovnaká deklarácia napája validáciu, OpenAPI aj API konzolu.

Viac o štýle s DTO: [telo, súbory a content typy](/sk/guide/typed-input-body), [konverzia, castery a validačné obmedzenia](/sk/guide/typed-input-conversion).

## params() alebo DTO

Handler deklaruje svoj vstup jedným z dvoch rovnocenných spôsobov. Oba sú plne podporované, zdieľajú registráciu, autorizáciu, obmedzenia požiadaviek, výstupy, OpenAPI aj konzolu a v jednej aplikácii sa dajú ľubovoľne miešať.

| | `params()` | Typované DTO |
| --- | --- | --- |
| Handler | `BaseHandler` s `params()` a `handle(array $params)` | `TypedHandler` s `handleDto(SomeInput $input)` |
| Deklarujete | zoznam objektov `InputParam` | readonly triedu s atribútmi na properties konštruktora |
| Handler dostane | pole zvalidovaných hodnôt | typovaný objekt |
| Konverzia | konverzia `InputType` z [Vstupných parametrov](/sk/guide/params) | striktná konverzia z [typovaného vstupu](/sk/guide/typed-input-conversion) |
| Vnorené telá | `JsonInputParam` s JSON schémou | vnorené DTO a zoznamy DTO, validované rekurzívne |
| Vlastný zdroj vstupu | ľubovoľná podtrieda `InputParam` | nie je k dispozícii, existuje päť atribútov zdroja |

`params()` zvoľte, keď endpoint spolieha na jeho voľnejšiu konverziu, na vlastný `InputParam` alebo na ručné spracovanie nezvyčajného tela. DTO zvoľte, keď chcete typované hodnoty, vnorené telá, validačné obmedzenia cez viac polí alebo celý kontrakt požiadavky v jednej triede. Ani jeden štýl nie je zastaraný a voľba štýlu pre jeden endpoint neovplyvní žiadny iný. [params() a DTO vedľa seba](/sk/guide/input-styles) ukazuje ten istý endpoint napísaný oboma štýlmi.

`TypedHandler` prevedie deklaráciu DTO na tie isté objekty `InputParam`, ktoré vracia `params()`.

## Najmenší handler

Rozšírte `TypedHandler` a v `handleDto()` deklarujte práve jeden nenulovateľný parameter DTO. Trieda parametra `handleDto()` je vstupné DTO; netreba ju opakovať v atribúte ani konfigurácii.

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

| Atribút | Zdroj | Typ vygenerovaného parametra |
| --- | --- | --- |
| `#[Query]` | query string | `InputParam::TYPE_GET`, OpenAPI `in: query` |
| `#[Path]` | nájdená cesta endpointu | `InputParam::TYPE_PATH`, OpenAPI `in: path` |
| `#[Header]` | HTTP hlavička | `InputParam::TYPE_HEADER`, OpenAPI `in: header` |
| `#[Body]` | pole JSON, formulárového alebo multipart tela | `InputParam::TYPE_BODY`, časť `requestBody` |
| `#[File]` | nahraný súbor | `InputParam::TYPE_FILE`, multipart `requestBody` |

Každá property DTO sa stane jedným `MappedInputParam`, teda `InputParam`, ktorý validuje a mapuje cez metadáta DTO. Kód, ktorý skúma `params()`, kontroluje `getType()`, nie konkrétnu triedu.

Napríklad `#[Header('X-Request-Id')] public ?string $requestId = null` číta `X-Request-Id`, nie `requestId`. Vyhľadávanie hlavičky nerozlišuje veľkosť písmen. Názov v `#[Path]` začína písmenom alebo podčiarkovníkom a má písmená, číslice a podčiarkovníky, pretože sa píše ako segment `{name}`; názov v `#[Header]` používa pomlčky, nie podčiarkovníky (`X-Request-Id`, nie `X_Request_Id`), pretože PHP sprístupňuje názvy hlavičiek s pomlčkami. Čokoľvek iné je chybou kompilácie s opraveným názvom. Hodnota z cesty alebo hlavičky je jeden reťazec, preto je tam natívny zoznam alebo vnorené DTO chybou kompilácie; rozdeľte ju casterom, napríklad `#[Header('X-Tags')] #[Cast(Split::class, ',')] public array $tags`.

`PathInputParam` a `HeaderInputParam` sú verejné aj pre handlery, ktoré používajú `params()`, pozrite [Vstupné parametre](/sk/guide/params#parametre-path-header-a-body).

`#[Header]` nepoužívajte pre hlavičku, ktorú proxy nastavuje alebo odstraňuje ako nositeľku dôvery, napríklad `X-Forwarded-User`. PHP a webservery mapujú `X_Request_Id` aj `X-Request-Id` na rovnaký názov, takže klient môže poslať podobne vyzerajúcu hlavičku. Takéto hodnoty overte v proxy a kópiu od klienta tam zahoďte. Hlavička `Authorization` sa číta aj z `REDIRECT_HTTP_AUTHORIZATION`, ktorú Apache s CGI alebo FastCGI nastaví po prepise, napríklad `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`. `BearerTokenAuthorization` ju číta rovnako, takže token, ktorý sa dostane do `#[Header('Authorization')]`, sa dostane aj do autorizácie. Ostatné hlavičky sa čítajú zo serverových premenných `HTTP_*`.

### Hodnoty z cesty a šablóny URL

Hodnota z cesty sa deklaruje v URL endpointu ako `{name}`, napríklad `users/{id}/detail`. Je povinná, nemôže byť nulovateľná ani mať predvolenú hodnotu a placeholder v OpenAPI ceste má rovnaký názov. Chýbajúci alebo nedeklarovaný parameter cesty je chybou kompilácie kontajnera. Statické URL endpointov fungujú bez zmeny.

Šablónu zaregistrujte ako akciu endpointu niektorým zo zvyčajných spôsobov:

```yaml
api:
	endpoints:
		- {method: GET, version: 1, package: users, action: '{id}/detail', handler: App\Api\UserDetailHandler}
```

```php
#[Get(1, 'users', '{id}/detail')]
final class UserDetailHandler extends TypedHandler
```

```php
$apiDecider->addApi(new EndpointIdentifier('GET', 1, 'users', '{id}/detail'), new UserDetailHandler(), new NoAuthorization());
```

Route musí prepustiť aj dodatočný segment, ako to robí route v [príklade typovaného vstupu](/sk/examples/typed-input): `/api/v<version>/<package>[/<apiAction>][/<params>]`. Požiadavka sa hľadá najprv medzi statickými endpointmi a potom medzi šablónami. Ak jednej URL vyhovujú dve šablóny, požiadavka skončí s `LogicException`, ktorá pomenuje obe, takže zmeňte statický segment tak, aby sa líšili.

### Povinné hodnoty a predvolené hodnoty

Nenulovateľná property bez predvolenej hodnoty je povinná. Nulovateľná property alebo property s predvolenou hodnotou je voliteľná. Chýbajúca nulovateľná hodnota sa stane `null`; chýbajúca hodnota s predvolenou hodnotou použije túto hodnotu. Rovnako sa nastaví `required` v OpenAPI.

Povinné parametre cesty nemôžu byť nulovateľné ani mať predvolenú hodnotu. Voliteľné pole tela akceptuje vynechanie poľa, zatiaľ čo explicitné JSON `null` akceptuje len nulovateľná property. Prázdny reťazec sa potichu nemení na `null`. Predvolená hodnota je `null`, skalár, pole alebo case backed enumu; objekt ako predvolená hodnota, napríklad `= new Inner()`, je chybou kompilácie, pretože predvolené hodnoty sa ukladajú do skompilovaného kontajnera. Použite `= null` a objekt vytvorte v handleri.

## Kompletný príklad požiadavky

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


Požiadavka s telom, vnoreným DTO a zoznamom je v stránke [telo, súbory a content typy](/sk/guide/typed-input-body#post-s-vnorenym-dto-a-polom).

## Chybové odpovede

Obálka odpovede ostáva rovnaká ako pri `ParamsProcessor`. Kľúč detailu je cesta poľa: bodky vstupujú do vnorených DTO a hranaté zátvorky označujú položky zoznamu. Najvyššie zdroje používajú svoje externé názvy.

```json
{
  "status": "error",
  "message": "wrong input",
  "detail": {
    "customerEmail": ["Email must be a valid email address"],
    "address.postalCode": ["Value must match the pattern /^[0-9]{5}$/"],
    "items[1].quantity": ["Value must be at least 1"],
    "deliveryAt": ["DateTime must be a valid RFC 3339 date and time"]
  }
}
```

Pri vypnutých detailoch chýb sa vrátia len `status` a `message`. So zapnutými Problem Details sa rovnaké cesty polí a hlášky objavia v jeho rozšírení pre chyby vstupu. Pokazené JSON a nečitateľné telo zachovajú existujúce chyby parsera tela.

Chýbajúci vnorený objekt nahlási cestu vnoreného objektu. Neznáme vstupné polia sa ignorujú, rovnako ako pri bežných query/formulárových parametroch; potichu sa nemapujú na properties.

## OpenAPI a API konzola

`TypedHandler::params()` sprístupní vygenerované objekty `InputParam`, takže existujúca konzola automaticky získa popisky, označenie povinnosti, predvolené hodnoty, príklady, voľby enumov a polia pre súbory. Konzola pošle každú hodnotu do jej zdroja: hodnoty z cesty sa URL-enkódujú do segmentov `{name}`, hodnoty hlavičiek sa stanú hlavičkami požiadavky a hodnoty tela sa pošlú v prvom media type, ktorý handler číta a konzola ho vie zostaviť: JSON s deklarovanými typmi (pole s vnoreným DTO berie JSON text, pole so zoznamom má jeden vstup na položku a položka DTO berie JSON text), URL-enkódovaný formulár alebo multipart. Súbor vždy znamená multipart, s názvami so zátvorkami pre vnorené polia. Neexistuje osobitný DTO režim konzoly, pozrite [Webová konzola](/sk/guide/console).

V OpenAPI sa query, path a header properties stanú parametrami operácie. Properties tela sa spoja do jednej objektovej schémy; vnorené DTO a zoznamy sa stanú vnorenými schémami. Predvolene `requestBody.content` obsahuje `application/json` aj `application/x-www-form-urlencoded`, alebo JSON a `multipart/form-data`, ak DTO obsahuje súbor. `#[Consumes]` obmedzí tento zoznam aj akceptované požiadavky na explicitné media typy. Operácia s telom dokumentuje aj odpoveď `415`. `nullable: true` sa objaví len pri properties tela: query reťazec, segment cesty ani hlavička nemôžu niesť `null`, takže voliteľná hodnota z query alebo hlavičky jednoducho chýba. Nulovateľný enum uvádza `null` vo svojom `enum`, ako to vyžaduje OpenAPI 3.0.3.

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

### Dlho bežiace workery

Hodnoty požiadavky typovaného handlera (telo, súbory, hodnoty z cesty a hlavičiek) sa držia v statickom stave pre aktuálnu požiadavku. `ApiPresenter::run()` ho vynuluje na začiatku každej požiadavky, takže PHP-FPM, RoadRunner, worker režim FrankenPHP aj Swoole vidia len aktuálnu požiadavku, pokiaľ každá požiadavka prechádza cez `ApiPresenter` a worker spracúva jednu požiadavku naraz. Súbežné požiadavky v jednom procese, napríklad prekladané Swoole korutiny, nie sú podporované, pretože stav je spoločný pre celý proces. Kód, ktorý vo workeri volá `params()` typovaného handlera mimo presentera, napríklad test alebo CLI príkaz, najprv zavolá `Tomaj\NetteApi\Input\RequestInput::reset()` a `Tomaj\NetteApi\Params\PathValues::reset()`.


## Bezpečnostné poznámky

- **Mapovanie beží pred autorizáciou.** Vstup sa konvertuje a validuje skôr, ako sa skontroluje autorizácia, a čaká až odpoveď: klient bez prístupu dostane `401`, nie chyby polí. Obmedzenie požiadaviek beží stále ako prvé. Caster alebo validačné obmedzenie je teda dostupné bez prihlásenia, preto ich držte čisté a lacné: žiadny prístup k databáze, sieti ani súborom. Na každý zoznam dajte `#[Length(max: ...)]`. Jedna požiadavka nahlási najviac 100 chýb spolu, nech sú zoznamy a objekty vnorené akokoľvek hlboko; zvyšok tela sa nekontroluje.
- **Veľkosť tela obmedzte na webservery.** `#[Length(max: ...)]` obmedzí zoznam skôr, ako sa jeho položky mapujú, ale telo sa dekóduje ako prvé a zoznam malých objektov zaberie v PHP oveľa viac pamäte než v požiadavke (zhruba 75-krát pri zozname čísel). Obmedzte veľkosť tam, kde požiadavka vstupuje: `client_max_body_size` v nginx, `LimitRequestBody` v Apache a `post_max_size` s `memory_limit` v PHP.
- **`#[Header]` nie je dôkaz pôvodu.** Pozrite poznámku pod [atribútmi zdroja](#atributy-zdroja).
- **Formuláre a cookies.** DTO s poľami tela predvolene prijíma URL-enkódované formuláre, ktoré môže odoslať ľubovoľný web bez CORS preflightu. Pre endpoint autentifikovaný cookie alebo adresou klienta použite `#[Consumes('application/json')]`, pozrite [telo, súbory a content typy](/sk/guide/typed-input-body#formulare-a-endpointy-autentifikovane-cez-cookie).

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
    ├── ListUsersHandler.php       # query, enum s hodnotou a predvolené hodnoty
    ├── UserDetailHandler.php      # path id a hlavička X-Request-Id
    ├── CreateOrderHandler.php     # JSON/formulárové telo, vnorené DTO a list<ItemInput>
    ├── AvatarUploadHandler.php    # multipart telo a FileUpload
    └── Input/                     # readonly DTO a UserStatus
```

End-to-end test pokrýva úspešné JSON, formulárové a multipart požiadavky, predvolené hodnoty, konverziu enumov, každý atribút zdroja, striktné zamietnutie `1a`, cesty vnorených chýb a vygenerovaný OpenAPI dokument. CI ho spúšťa spolu s ostatnými príkladmi.


## Ako je to postavené

- **Metadáta pri kompilácii.** `NetteApiExtension` reflektuje DTO a zapíše ich definície `InputParam` a metadáta mapovania do skompilovaného kontajnera. Požiadavky ani generovanie OpenAPI DTO znova nereflektujú a nepotrebujú žiadne nastavenie invalidácie cache; backing typy enumov sa cachujú pre každú triedu. Zmena DTO, jeho rodičovskej triedy, traitu, ktorý používa, casteru, validačného obmedzenia alebo enumu prebuduje kontajner rovnako ako zmena atribútov endpointov. Bez DI rozšírenia sa raz za požiadavku zostaví malý register vygenerovaných metadát; nepridáva sa žiadna runtime závislosť na Nette Cache.
- **Jeden pipeline metadát.** Metadáta DTO sa prekladajú na existujúce objekty `InputParam`, takže validácia, OpenAPI a konzola nemajú osobitné vetvy pre DTO.
- **Rekurzívna runtime validácia.** Spracuje JSON, formulár aj multipart rovnako a dáva presné cesty k properties. JSON schéma sa generuje len pre OpenAPI.
- **Iba readonly promoted properties.** DTO sa vytvoria raz cez konštruktor a mapper ich nemôže čiastočne meniť. Povinný parameter konštruktora, ktorý nie je promoted property, a variadické alebo referenčné parametre sú chybou kompilácie.
- **Striktná, explicitná konverzia skalárov.** Neplatné dáta od klienta sú `400`, nie prekvapivý cast.
- **Malá sada validačných obmedzení.** Range, Length, Pattern a Email pokrývajú bežné kontrakty API; vlastné validačné obmedzenia pokrývajú pravidlá aplikácie bez validačného frameworku.
- **Predvolene JSON aj formulár, `Consumes` obmedzuje.** Jedno DTO obslúži klientov s JSON aj formulárom a OpenAPI inzeruje rovnaké media typy; keď je prítomný súbor, multipart nahradí URL-enkódovaný formulár, ktorý súbory nezvládne. `Consumes` je atribút na úrovni triedy ako ostatné atribúty endpointov a jeho názov ho odlišuje od HTTP hlavičky `Accept`, ktorá sa týka odpovedí.
- **`handleDto(Input $input)` ako metóda handlera.** Názov hovorí, že ide o vstupný bod handlera, a reflexia číta triedu DTO z jeho parametra, takže netreba ďalší atribút ani konfiguráciu.
- **`Cast` s `CasterInterface`.** Formáty aplikácie ostávajú pri property, argumenty konštruktora pokrývajú voľby ako `d.m.Y` a `CastException` dáva chyby 400 s cestou k poľu. Voliteľné rozhranie nápovedy schémy necháva metadáta OpenAPI pre castery nepovinné.

