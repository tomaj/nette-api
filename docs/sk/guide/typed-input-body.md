---
title: 'Typovaný vstup: telo, súbory a content typy'
description: 'Polia tela typovaného DTO v Nette API: vnorené objekty a zoznamy, content typy JSON, formulár a multipart, Consumes, 415 a nahrávanie súborov.'
---

# Typovaný vstup: telo, súbory a content typy

Táto stránka popisuje polia tela typovaného DTO: vnorené objekty a zoznamy, content typy JSON, formulár a multipart, `#[Consumes]` a nahrávanie súborov. Základy sú v stránke [Typovaný vstup (DTO)](/sk/guide/typed-input).

## POST s vnoreným DTO a poľom

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
        #[Email]
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

Mapper DTO rekurzívne vytvorí `AddressInput` a oba objekty `ItemInput`. `list<ItemInput>` je povinný PHPDoc pre pole DTO; pre polia skalárov sú podporované `list<string>`, `list<int>`, `list<float>` a `list<bool>`. Čítajú sa bežné zápisy: `list<T>`, `non-empty-list<T>`, `T[]`, s `|null` alebo úvodným `?` (nulovateľnosť určuje typ v PHP) a `@phpstan-param` alebo `@psalm-param` namiesto `@param`. Položka je jeden nenulovateľný typ, preto `list<?int>` a `list<int|string>` sú chybou kompilácie; použite DTO položky. Asociatívne polia, union typy okrem nulovateľných, `mixed`, `object` a netypované polia sa odmietnu pri kompilácii kontajnera, nie v polovici požiadavky. Predvolená hodnota zoznamu sa v tom istom čase skontroluje po položkách, takže `@param list<int>` s predvolenou hodnotou `['x']` sa neskompiluje.

Rovnaké DTO funguje s URL-enkódovanými formulárovými poľami a s multipart poľami, ak má property `#[File]` alebo `#[Consumes('multipart/form-data')]`. Vnorené polia používajú PHP zápis s hranatými zátvorkami, napríklad `address[street]` a `items[0][sku]`; `deliveryAt` ostáva rovnaký RFC 3339 reťazec. Parsovanie tela závisí od content typu, nie od JSON schémy.

## Content typy tela

`TypedHandler` s poľami tela predvolene akceptuje **`application/json`** aj **`application/x-www-form-urlencoded`**. Ak jeho DTO obsahuje property `#[File]`, predvolené typy sú **`application/json`** a **`multipart/form-data`**: multipart nahradí URL-enkódovaný formulár, pretože ten nedokáže preniesť súbory, a súbory sa posielajú spolu s poľami tela. Content type sa porovnáva bez parametrov, takže `application/json; charset=utf-8` je akceptovaný. JSON sa vždy číta ako UTF-8: iný `charset`, napríklad `application/json; charset=ISO-8859-1`, je `400` namiesto nesprávneho dekódovania (akceptuje sa `utf-8`, jeho bežný zápis `utf8` a `us-ascii`). Telo, ktoré príde bez akéhokoľvek `Content-Type`, je `415` aj keď sú všetky polia voliteľné, inak by sa potichu zahodilo.

OpenAPI ponúka rovnaké možnosti v `requestBody.content`: JSON a URL-enkódovaný formulár alebo JSON a multipart pri súbore. Keď je súbor povinný, ponúka sa len multipart, pretože JSON požiadavka by mohla dostať len `400`. Properties so súbormi sú len v schéme `multipart/form-data`, pretože JSON nedokáže preniesť súbor. JSON požiadavka na DTO s povinným súborom dostane `400` s cestou poľa súboru a správou `File is required and can only be uploaded as multipart/form-data, but the request is application/json`. Polia tela sú parametre `TYPE_BODY` a súbory parametre `TYPE_FILE`; stále existuje len jedna cesta validácie a mapovania.

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

Endpoint iba na nahrávanie použije `#[Consumes('multipart/form-data')]`. Obmedzenie na formulárový typ bez `multipart/form-data` znamená, že endpoint nemôže prijať súbory; nemožná kombinácia, napríklad povinný `#[File]` s konzumáciou len JSON, je chybou kompilácie kontajnera.

Požiadavka s iným content typom sa pred mapovaním odmietne s **415 Unsupported Media Type**. 415 sa pošle až po autorizácii a obmedzeniach požiadaviek, takže klient bez prístupu dostane `401`, a `ApiLoggerInterface` ju zaloguje ako obslúženú požiadavku. Pri zapnutých detailoch chýb hláška uvádza prijatý typ aj presné akceptované typy, napríklad:

```json
{
  "status": "error",
  "message": "unsupported media type",
  "detail": "Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded."
}
```

Chýbajúci content type tela pri požiadavke, ktorá telo vyžaduje, dostane rovnakú zrozumiteľnú 415 namiesto neskoršej chyby null property. Pri zapnutých Problem Details sa použije status 415 a rovnaká hláška v tomto formáte a jeho `detail` vždy uvádza akceptované typy. Predvolená obálka `detail` vynechá, keď sú detaily chýb vypnuté, rovnako ako pri chybách polí.

### Rozdiely medzi JSON, formulárom a multipart

| Oblasť | JSON | URL-enkódovaný formulár / multipart |
| --- | --- | --- |
| Vnorené DTO | objekty: `{"address":{"street":"Main 1"}}` | názvy so zátvorkami: `address[street]=Main+1` |
| Zoznamy DTO | polia: `"items":[{"sku":"BOOK-1"}]` | indexované názvy: `items[0][sku]=BOOK-1` |
| Natívne skalárne typy | čísla a booleany ostávajú JSON číslami/booleanmi | hodnoty prichádzajú ako reťazce a prechádzajú rovnakou striktnou konverziou |
| Null | explicitné `null` je povolené len pre nulovateľnú property | neexistuje null literál: vynechajte pole, aby použilo null alebo predvolenú hodnotu |
| Prázdny reťazec | skutočný reťazec, nie null | skutočný reťazec, nie null |
| Objekt vs zoznam | `{}` je objekt, aj keď mu chýbajú properties. `[]` je zoznam a je zamietnutý všade, kde sa vyžaduje objekt, vrátane prázdneho zoznamu. JSON objekt je zamietnutý tam, kde sa vyžaduje zoznam | názvy so zátvorkami tvoria objekt (`address[street]`); indexované názvy tvoria zoznam (`items[0]`) |
| Súbory | nepodporované | len `multipart/form-data` |

Napríklad JSON `"quantity": 2` ostane integer, zatiaľ čo formulárové `quantity=2` sa stane integer až po tom, ako kompletný reťazec prejde striktnou konverziou. Ani jedno neakceptuje `2pcs`. JSON `false` a formulárové `remember=0` sú platné booleany; ľubovoľný neprázdny formulárový reťazec sa potichu nestane `true`.

Pre voliteľnú formulárovú hodnotu vynechajte `deliveryAt` namiesto poslania `deliveryAt=null` alebo prázdnej hodnoty. Multipart súbor nemožno nahradiť JSON base64 reťazcom; aplikácia, ktorá potrebuje base64 uploady, môže ponechať `BaseHandler` a spracovať vlastné raw telo.

PHP číta multipart telá len pri `POST`. Telo `multipart/form-data` poslané cez `PUT` alebo `PATCH` PHP nespracuje, preto endpoint odpovie `400` hláškou `multipart/form-data is only parsed by PHP for POST requests, but this one is PUT. Send it with POST, or use application/json`. Uploady a multipart formuláre posielajte cez `POST`. URL-enkódovaný formulár s viac poliami, než dovoľuje `max_input_vars` v PHP (predvolene 1000), je `400` s hláškou `Too many form fields`, pri `POST` aj pri `PUT` a `PATCH`. Multipart `POST` s väčším počtom polí PHP odreže skôr, než ho aplikácia uvidí, a nedá sa odlíšiť od úplného: pre takéto formuláre zvýšte `max_input_vars` alebo pošlite JSON.

## Formuláre a endpointy autentifikované cez cookie

URL-enkódovaný formulár je „jednoduchá“ požiadavka medzi webmi: stránka na ľubovoľnom webe ho môže odoslať do vášho API spolu s cookies návštevníka a bez CORS preflightu, čo telo JSON nedokáže. DTO s poľami tela formulár prijíma predvolene, takže endpoint autentifikovaný iba session cookie alebo adresou klienta sa dá zasiahnuť takým formulárom z cudzieho webu. Obmedzte ho na JSON cez `#[Consumes('application/json')]` a nastavte cookie `SameSite`. Endpointov autentifikovaných bearer tokenom alebo hlavičkou API kľúča sa to netýka, pretože formulár v prehliadači tieto hlavičky nastaviť nevie.


## Nahrávanie súboru

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

`FileUpload` je typovaný value object knižnice pre nahraný súbor. Sprístupňuje pôvodný názov, content type od klienta, dočasnú cestu, veľkosť a chybu nahrávania; neverí content typu od klienta ani automaticky nepresúva súbor. `?FileUpload` znamená voliteľný súbor a `list<FileUpload>` prijme viac súborov v jednom poli. Pred uložením skontrolujte chybu nahrávania, veľkosť a skutočný typ, rovnako ako pri `FileInputParam`. Chyby nahrávania ako `UPLOAD_ERR_INI_SIZE` sa nemenia na validačné chyby; `FileUpload` ich nesie v `error`. Za chýbajúci súbor sa považuje len chýbajúci záznam alebo `UPLOAD_ERR_NO_FILE` s prázdnym názvom, a pri `list<FileUpload>` keď majú všetky položky `UPLOAD_ERR_NO_FILE`. Požiadavky s nahrávaním musia používať `POST`, pozrite vyššie. `#[File]` patrí do vstupného DTO handlera; `#[File]` vo vnorenom DTO alebo v zozname DTO je chybou kompilácie, pretože PHP drží vnorené uploady v samostatnom strome `$_FILES` a polia tela sa nikdy nesmú čítať ako metadáta uploadu.

