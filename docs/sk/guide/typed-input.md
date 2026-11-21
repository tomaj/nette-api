---
title: Typovaný vstup (DTO)
description: 'Typované DTO požiadavky v Nette API: atribúty Query, Path, Header, Body a File, povinné hodnoty, chyby, OpenAPI a API konzola. Druhý, rovnocenný spôsob deklarácie vstupu handlera popri params().'
---

# Typovaný vstup (DTO)

Triedy na tejto stránke sú súčasťou Nette API 4.0. Spustiteľný projekt je v [`examples/typed-input`](/sk/examples/typed-input).

Typované vstupné DTO drží kontrakt požiadavky pri hodnotách, ktoré handler používa. Atribúty určujú, odkiaľ hodnota pochádza, typy properties určujú jej PHP typ a atribúty validačných obmedzení validáciu. Rovnaká deklarácia napája validáciu, OpenAPI aj API konzolu.

Viac o štýle s DTO: [telo, súbory a typy obsahu](/sk/guide/typed-input-body), [konverzia, castery a validačné obmedzenia](/sk/guide/typed-input-conversion).

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

`TypedHandler` prevedie deklaráciu DTO na objekty `InputParam`, takže validácia, OpenAPI aj konzola fungujú ako pri `params()`.

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

Atribúty sú v `Tomaj\NetteApi\Input` a cielia na promované properties konštruktora. Každá promovaná property má práve jeden atribút zdroja. Externý názov je predvolene názov property; odovzdaním názvu ho zmeníte. `description` a `examples` (pole, ktorého kľúče sú názvy príkladov, ako pri `InputParam::addExample()`) sa stanú popisom a príkladmi vygenerovaného `InputParam`.

| Atribút | Zdroj | Typ vygenerovaného parametra |
| --- | --- | --- |
| `#[Query]` | query string | `InputParam::TYPE_GET`, OpenAPI `in: query` |
| `#[Path]` | segment cesty endpointu | `InputParam::TYPE_PATH`, OpenAPI `in: path` |
| `#[Header]` | HTTP hlavička | `InputParam::TYPE_HEADER`, OpenAPI `in: header` |
| `#[Body]` | pole JSON, formulárového alebo multipart tela | `InputParam::TYPE_BODY`, časť `requestBody` |
| `#[File]` | nahraný súbor | `InputParam::TYPE_FILE`, multipart `requestBody` |

Každá property koreňového DTO sa stane jedným `MappedInputParam`, teda `InputParam`, ktorý validuje a mapuje cez metadáta DTO. Kód, ktorý skúma `params()`, kontroluje `getType()`, nie konkrétnu triedu.

Napríklad `#[Header('X-Request-Id')] public ?string $requestId = null` číta `X-Request-Id`, nie `requestId`. Vyhľadávanie hlavičky nerozlišuje veľkosť písmen. Názov v `#[Path]` začína písmenom alebo podčiarkovníkom a má písmená, číslice a podčiarkovníky, pretože sa píše ako segment `{name}`; názov v `#[Header]` používa pomlčky, nie podčiarkovníky (`X-Request-Id`, nie `X_Request_Id`), pretože názvy HTTP hlavičiek používajú pomlčky. `Accept`, `Content-Type` a `Authorization` nemôžu byť názvami v `#[Header]`, pretože ich OpenAPI neopisuje ako parametre: media typy tela určuje `#[Consumes]` a prihlasovacie údaje autorizácia endpointu. Čokoľvek iné je chybou kompilácie s opraveným názvom. Hodnota z cesty alebo hlavičky je jeden reťazec, preto je tam natívny zoznam alebo vnorené DTO chybou kompilácie; rozdeľte ju casterom, napríklad `#[Header('X-Tags')] #[Cast(Split::class, ',')] public array $tags`.

`PathInputParam`, `HeaderInputParam` a `BodyInputParam` sú verejné aj pre handlery, ktoré používajú `params()`, pozrite [Vstupné parametre](/sk/guide/params#parametre-path-header-a-body).

### Hodnoty z cesty a šablóny URL

Hodnota z cesty sa deklaruje v URL endpointu ako `{name}`, napríklad `users/{id}/detail`. Je povinná, nemôže byť nulovateľná ani mať predvolenú hodnotu a placeholder v OpenAPI ceste má rovnaký názov. Segment `{name}` bez property `#[Path]` alebo property `#[Path]`, ktorá v URL chýba, zastaví s DI rozšírením kompiláciu kontajnera; s `addApi()` vyhodí `LogicException` pri prvom použití endpointu. Rovnaká kontrola platí aj pre `PathInputParam` handlera s `params()`. Statické URL endpointov fungujú bez zmeny. Hodnota z cesty je jeden segment URL, preto nemôže obsahovať lomítko: požiadavka so zakódovaným lomítkom (`%2F`) v ceste nezodpovedá žiadnej šablóne.

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

Routa musí prepustiť segmenty šablóny. Routa z [príkladu typovaného vstupu](/sk/examples/typed-input), `/api/v<version>/<package>[/<apiAction>][/<params>]`, prepustí za akciou jeden segment; hlbšia šablóna ako `{id}/orders/{orderId}` potrebuje v maske routy `[/<params .+>]`, inak Nette odpovie `404` skôr, ako požiadavka dorazí do API. Požiadavka sa hľadá najprv medzi statickými endpointmi a potom medzi šablónami. Keď má URL za akciou ďalšie segmenty, šablóna, ktorá zodpovedá celej ceste, vyhrá nad kratším statickým endpointom: `/users/7/detail` pôjde na `users/{id}/detail`, nie na statický `users/7`. Dve šablóny, ktoré môžu zodpovedať tej istej URL, zastavia s DI rozšírením kompiláciu kontajnera a `addApi()` vyhodí `LogicException`, ktorá pomenuje obe. Zmeňte statický segment tak, aby sa líšili.

### Povinné hodnoty a predvolené hodnoty

Nenulovateľná property bez predvolenej hodnoty je povinná. Nulovateľná property alebo property s predvolenou hodnotou je voliteľná. Chýbajúca nulovateľná hodnota sa stane `null`; chýbajúca hodnota s predvolenou hodnotou použije túto hodnotu. Rovnako sa nastaví `required` v OpenAPI.

Voliteľné pole tela akceptuje vynechanie poľa, zatiaľ čo explicitné JSON `null` akceptuje len nulovateľná property. Prázdny reťazec sa potichu nemení na `null`. Predvolená hodnota je `null`, skalár, pole alebo case backed enumu; objekt ako predvolená hodnota, napríklad `= new Inner()`, je chybou kompilácie. Použite `= null` a objekt vytvorte v handleri.

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

Požiadavka s telom, vnoreným DTO a zoznamom je na stránke [telo, súbory a typy obsahu](/sk/guide/typed-input-body#post-s-vnorenym-dto-a-zoznamom).

## Chybové odpovede

Chyby používajú rovnakú obálku ako handlery s `params()`. Kľúč detailu je cesta poľa: bodky vstupujú do vnorených DTO a hranaté zátvorky označujú položky zoznamu. Polia najvyššej úrovne majú kľúč podľa svojho externého názvu.

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

Pri vypnutých detailoch chýb sa vrátia len `status` a `message`. So zapnutými Problem Details sa rovnaké cesty polí a hlášky objavia v jeho zozname `errors` ako `parameter` a `detail`.

Chyby tela ako celku, napríklad pokazené JSON (`Syntax error`), iný charset než UTF-8 alebo nečitateľné telo, sa objavia pod `body`, preto žiadne pole nemôže mať vstupný názov `body`: kompilácia takého DTO zlyhá, dajte property iný vstupný názov, napríklad `#[Body('payload')]`.

Chýbajúci vnorený objekt nahlási cestu vnoreného objektu. Neznáme vstupné polia sa ignorujú, rovnako ako pri bežných query a formulárových parametroch.

## OpenAPI a API konzola

[Webová konzola](/sk/guide/console) zostaví formulár z vygenerovaných objektov `InputParam` a každú hodnotu pošle do jej zdroja; osobitný DTO režim neexistuje.

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

JSON schéma sa tu generuje len ako výstupný formát OpenAPI. Runtime validácia používa rekurzívny mapper DTO a nikdy nevyžaduje JSON schému.

## Bezpečnostné poznámky

- **Mapovanie beží pred autorizáciou.** Vstup sa konvertuje a validuje skôr, ako sa skontroluje autorizácia, a na autorizáciu čaká až samotná odpoveď: klient bez prístupu dostane `401`, nie chyby polí. Obmedzenie požiadaviek aj tak beží ako prvé. Caster alebo validačné obmedzenie je teda dostupné bez prihlásenia, preto ich držte čisté a lacné: žiadny prístup k databáze, sieti ani súborom. Na každý zoznam dajte `#[Length(max: ...)]`.
- **Veľkosť tela obmedzte na webservery.** `#[Length(max: ...)]` obmedzí zoznam skôr, ako sa jeho položky mapujú, ale telo sa dekóduje ako prvé a dekódovaný zoznam zaberie v PHP mnohonásobne viac pamäte než v požiadavke. Obmedzte veľkosť tam, kde požiadavka vstupuje: `client_max_body_size` v nginx, `LimitRequestBody` v Apache a `post_max_size` s `memory_limit` v PHP.
- **`#[Header]` nie je dôkaz pôvodu.** Nepoužívajte ho pre hlavičku, ktorú proxy nastavuje alebo odstraňuje ako nositeľku dôvery, napríklad `X-Forwarded-User`. PHP a webservery mapujú `X_Request_Id` aj `X-Request-Id` na rovnaký názov, takže klient môže poslať podobne vyzerajúcu hlavičku. Takéto hodnoty overte v proxy a kópiu od klienta tam zahoďte.
- **Formuláre a cookies.** DTO s poľami tela predvolene prijíma URL-enkódované formuláre, ktoré môže odoslať ľubovoľný web bez CORS preflightu. Pre endpoint autentifikovaný cookie alebo adresou klienta použite `#[Consumes('application/json')]`, pozrite [telo, súbory a typy obsahu](/sk/guide/typed-input-body#formulare-a-endpointy-autentifikovane-cez-cookie).

## Skompilované metadáta a workery

S DI rozšírením sa metadáta DTO skompilujú do kontajnera a zmena DTO, casteru, validačného obmedzenia alebo enumu ho prebuduje. Konštanta inej triedy použitá v argumente atribútu alebo v predvolenej hodnote sa nesleduje; po jej zmene vymažte cache. Bez rozšírenia sa metadáta zostavia raz za proces.

Každé čítanie `params()` typovaného handlera spracuje aktuálnu požiadavku znova a hodnoty z cesty nastaví `ApiDecider` pre každú požiadavku, takže workery (RoadRunner, FrankenPHP, Swoole) sú v poriadku, pokiaľ každý spracúva jednu požiadavku naraz; súbežné korutiny v jednom procese podporované nie sú.

## Príklad

Spustiteľný projekt so všetkými atribútmi zdroja, vnoreným telom a nahrávaním súboru popisuje [príklad typovaného vstupu](/sk/examples/typed-input).
