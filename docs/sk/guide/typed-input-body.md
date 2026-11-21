---
title: 'Typovaný vstup: telo, súbory a typy obsahu'
description: 'Polia tela typovaného DTO v Nette API: vnorené objekty a zoznamy, typy obsahu JSON, formulár a multipart, Consumes, 415 a nahrávanie súborov.'
---

# Typovaný vstup: telo, súbory a typy obsahu

Táto stránka popisuje polia tela typovaného DTO: vnorené objekty a zoznamy, typy obsahu JSON, formulár a multipart, `#[Consumes]` a nahrávanie súborov. Základy sú na stránke [Typovaný vstup (DTO)](/sk/guide/typed-input).

## POST s vnoreným DTO a zoznamom

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

Mapper DTO rekurzívne vytvorí `AddressInput` a oba objekty `ItemInput`. Zoznam DTO potrebuje PHPDoc, napríklad `list<ItemInput>`, a zoznam skalárov `list<string>`, `list<int>`, `list<float>` alebo `list<bool>` (fungujú aj `T[]`, `@phpstan-param` a `@psalm-param`; `non-empty-list<T>` sa číta ako `list<T>`, takže na vyžadovanie položiek pridajte `#[Length(min: 1)]`); asociatívne polia, union typy, `mixed`, `object` a netypované polia zlyhajú pri kompilácii kontajnera.

Rovnaké DTO funguje s URL-enkódovanými formulárovými poľami a s multipart poľami, ak má property `#[File]` alebo `#[Consumes('multipart/form-data')]`. Vnorené polia používajú PHP zápis s hranatými zátvorkami, napríklad `address[street]` a `items[0][sku]`; `deliveryAt` ostáva rovnaký RFC 3339 reťazec. Parsovanie tela závisí od typu obsahu, nie od JSON schémy.

OpenAPI toto telo zdokumentuje takto (odpovede sú vynechané):

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

Endpoint prijme aj formulár, ale OpenAPI 3.0 nevie opísať zoznam objektov vo formulárovom tele, preto je tu uvedený len JSON. DTO, ktoré má aj `#[File]`, si `multipart/form-data` ponechá, lebo súbor sa inak poslať nedá; jeho multipart schéma uvedie také pole pod obyčajným názvom a v popise povie, že sa posiela v zátvorkovej notácii (`items[0][sku]`). DTO, ktorého telo má len skaláry, zoznamy skalárov a objekty skalárov, uvádza oba media typy; formulárová schéma pomenuje zoznam `tags[]` a objekt posiela ako `deepObject`. OpenAPI použije `deepObject` len pre URL-enkódovaný formulár, preto multipart schéma uvedie polia objektu jednotlivo (`address[street]`):

```openapi
requestBody:
  content:
    application/json:
      schema:
        type: object
        properties:
          tags: {type: array, items: {type: string}}
          address: {type: object, properties: {street: {type: string}}}
    application/x-www-form-urlencoded:
      schema:
        type: object
        properties:
          'tags[]': {type: array, items: {type: string}}
          address: {type: object, properties: {street: {type: string}}}
      encoding:
        address: {style: deepObject, explode: true}
```

## Typy obsahu tela

`TypedHandler` s poľami tela predvolene akceptuje **`application/json`** a **`application/x-www-form-urlencoded`**, alebo **`application/json`** a **`multipart/form-data`**, keď jeho DTO obsahuje property `#[File]`; OpenAPI uvádza rovnaké typy v `requestBody.content` (pri povinnom súbore len multipart). Typ obsahu sa porovnáva bez parametrov. JSON musí byť v UTF-8: požiadavka, ktorá uvedie iný `charset`, je `400`. Telo bez `Content-Type` alebo s typom, ktorý handler neprijíma, je `415`; požiadavka úplne bez tela prejde, keď nie je povinné žiadne pole tela, nech je jej `Content-Type` akýkoľvek. Properties so súbormi sú len v schéme `multipart/form-data` a JSON požiadavka na DTO s povinným súborom dostane `400`, ktorá vysvetlí, že súbory potrebujú `multipart/form-data`.

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

Endpoint iba na nahrávanie použije `#[Consumes('multipart/form-data')]`. DTO s property `#[File]`, povinnou aj voliteľnou, musí mať v `#[Consumes]` uvedený `multipart/form-data`; inak zlyhá kompilácia kontajnera.

Požiadavka s iným typom obsahu sa pred mapovaním odmietne s **415 Unsupported Media Type**. 415 sa pošle až po autorizácii a obmedzeniach požiadaviek, takže klient bez prístupu dostane namiesto nej `401`. Pri zapnutých detailoch chýb hláška uvádza prijatý typ aj presné akceptované typy, napríklad:

```json
{
  "status": "error",
  "message": "unsupported media type",
  "detail": "Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded."
}
```

So zapnutými Problem Details má 415 tento formát a jeho `detail` vždy uvádza akceptované typy.

### Rozdiely medzi JSON, formulárom a multipart

| Oblasť | JSON | URL-enkódovaný formulár / multipart |
| --- | --- | --- |
| Vnorené DTO | objekty: `{"address":{"street":"Main 1"}}` | názvy so zátvorkami: `address[street]=Main+1` |
| Zoznamy DTO | polia: `"items":[{"sku":"BOOK-1"}]` | indexované názvy: `items[0][sku]=BOOK-1` |
| Natívne skalárne typy | čísla a booleany ostávajú JSON číslami/booleanmi | hodnoty prichádzajú ako reťazce a prechádzajú rovnakou striktnou konverziou |
| Null | explicitné `null` je povolené len pre nulovateľnú property | neexistuje null literál: vynechajte pole, aby použilo null alebo predvolenú hodnotu |
| Prázdny reťazec | skutočný reťazec, nie null | skutočný reťazec, nie null |
| Objekt vs zoznam | `{}` je objekt (jeho chýbajúce properties sa nahlásia jednotlivo). `[]` je zoznam a je zamietnutý všade, kde sa vyžaduje objekt, vrátane prázdneho zoznamu. JSON objekt je zamietnutý tam, kde sa vyžaduje zoznam | názvy so zátvorkami tvoria objekt (`address[street]`); indexované názvy tvoria zoznam (`items[0]`) |
| Súbory | nepodporované | len `multipart/form-data` |

Napríklad JSON `"quantity": 2` ostane integer, zatiaľ čo formulárové `quantity=2` sa stane integer až po tom, ako kompletný reťazec prejde striktnou konverziou. Ani jedno neakceptuje `2pcs`. JSON `false` a formulárové `remember=0` sú platné booleany; ľubovoľný neprázdny formulárový reťazec sa potichu nestane `true`.

Pre voliteľnú formulárovú hodnotu vynechajte `deliveryAt` namiesto poslania `deliveryAt=null` alebo prázdnej hodnoty. Multipart súbor nemožno nahradiť JSON base64 reťazcom; aplikácia, ktorá potrebuje base64 uploady, môže ponechať `BaseHandler` a spracovať vlastné raw telo.

Multipart posielajte cez `POST`. Formuláre nad `max_input_vars` sa odmietnu s `400`. PHP si multipart telo neponechá, aby sa dalo spočítať znova, preto sa odmietne aj multipart formulár s presne `max_input_vars` poľami; pri takých veľkých formulároch limit zvýšte.

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

`FileUpload` má properties `originalName`, `clientContentType`, `temporaryPath`, `size` a `error` (PHP kód `UPLOAD_ERR_*`). `?FileUpload` je voliteľný súbor a `list<FileUpload>` prijme viac súborov v jednom poli; súborové polia zoznamu, ktoré klient nechal prázdne, v ňom nebudú. Handler pred uložením súboru skontroluje chybu nahrávania, veľkosť a skutočný typ; nič sa nepresúva automaticky. `#[File]` je povolený len v koreňovom vstupnom DTO handlera, nie vo vnorenom DTO ani v zozname.

