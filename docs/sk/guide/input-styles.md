---
title: params() a DTO vedľa seba
description: 'Rovnaké endpointy deklarované cez params() aj cez typované DTO v Nette API: sedem dvojíc ukazuje, ako prepísať handler z jedného štýlu na druhý.'
---

# params() a DTO vedľa seba

`params()` s `handle(array $params)` a `TypedHandler` s DTO sú dva rovnocenné spôsoby písania handlerov v jednej aplikácii. Jeden nevypína druhý. Registrácia, autorizácia, výstupy, obmedzenia požiadaviek, OpenAPI a konzola sú spoločné. [Typovaný vstup (DTO)](/sk/guide/typed-input) oba štýly porovnáva a [Vstupné parametre](/sk/guide/params) popisujú prvý z nich.

## Prepisovanie medzi štýlmi

Nasledujúce dvojice ukazujú rovnaký kontrakt požiadavky v oboch štýloch, takže handler môžete prepísať z ktoréhokoľvek štýlu na druhý, keď sa to pre daný endpoint hodí. Importy frameworku sú vynechané tam, kde sa nemenia; závislosti aplikácie v konštruktore a vytváranie odpovede ostávajú v handleri. Môžete prepísať jeden endpoint, všetky ostatné handlery nechať bez zmeny a ďalší prepísať, keď sa vám to bude hodiť. Neexistuje globálny prepínač aplikácie.

### 1. Jednoduchý GET: voliteľné/predvolené query parametre a enum

S `params()` handler deklaruje každý query parameter, pretypuje hodnoty a mapuje reťazec statusu na enum. `UserStatus::options()` zastupuje pomocnú metódu aplikácie, ktorá vracia povolené hodnoty:

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

S DTO sú predvolené hodnoty, voliteľnosť a povolené hodnoty enumu v triede. `Range` nahradí ručnú kontrolu hraníc a `handleDto()` dostane hodnoty pripravené na použitie:

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

S `params()` URL endpointu obsahuje `users/{id}/detail` a `params()` deklaruje `PathInputParam` a `HeaderInputParam`:

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

S DTO `Path` a `Header` zachovajú externé názvy, zatiaľ čo handler používa výstižné typované properties:

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

S `params()` obsahuje `JsonInputParam` ručne písanú schému. Handler číta vnorené polia a vytvára vlastné hodnoty:

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

S DTO je vnorená štruktúra vyjadrená raz ako triedy. Runtime validácia nepotrebuje JSON schému:

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

S `params()` objekty `PostInputParam` popisujú URL-enkódovaný prihlasovací formulár:

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

S DTO `Body` znamená pole tela bez ohľadu na to, či je požiadavka JSON alebo formulárová. Hranica `max` je tu jediné pravidlo dĺžky, pretože minimum by pri prihlásení začalo odmietať existujúce krátke heslá:

```php
final readonly class LoginInput
{
    public function __construct(
        #[Body] #[Email] public string $email,
        #[Body] #[Length(max: 200)] public string $password,
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

S `params()` handler dostane surovú položku uploadu a validuje jej chybu nahrávania a veľkosť:

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

S DTO `FileUpload` poskytne typovaný prístup k rovnakým metadátam. Aplikačné kontroly skutočného typu súboru a ukladania ostávajú explicitné:

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

Názvy multipart polí ostávajú `displayName` a `avatar`. Ani jeden štýl nenahrádza bezpečnostné kontroly aplikácie ani PHP limity uploadov.

### 6. Vlastná validácia

S `params()` žije validácia závislá od viacerých hodnôt v `handle()`:

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

S DTO sú jednoduchá konverzia a RFC 3339 parsovanie deklaratívne. Vlastné validačné obmedzenie vyjadruje pravidlo, ktoré spája viac polí, a zachováva konzistentnú transportnú odpoveď:

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

`ConstraintContext::value()` číta susedné hodnoty v tom istom DTO, takže validačné obmedzenia medzi properties nemusia skúmať HTTP požiadavku. Obchodné pravidlá, ktoré potrebujú databázu, stále patria do aplikačných služieb; validačné obmedzenie má ostať deterministickou kontrolou kontraktu požiadavky.

### 7. Oba štýly v jednej aplikácii

Endpoint môže závisieť od voľnejšej konverzie `InputType::INTEGER` v `params()`, od vlastného `InputParam` alebo od ručného parsovania nezvyčajného content typu. Pre taký endpoint je `params()` správny štýl:

```php
final class ReportHandler extends BaseHandler
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

Susediaci `ListReportsHandler` môže rozširovať `TypedHandler`. Miešanie štýlov je podporované bez obmedzenia; voľba sa týka pohodlia pri každom endpointe.

### Kontrolný zoznam prepisu handlera na DTO

1. **Zachyťte aktuálny kontrakt:** uložte testy endpointu a aktuálnu OpenAPI operáciu vrátane predvolených hodnôt a chybového správania.
2. **Vytvorte readonly DTO:** jednu promovanú property pre každý query, path, header, body alebo file vstup.
3. **Zachovajte externé názvy:** použite explicitné názvy atribútov ako `#[Header('X-Request-Id')]`, ak sa názov property líši.
4. **Zlaďte povinnosť:** nenulovateľná property bez predvolenej hodnoty je povinná; nulovateľná alebo s predvolenou hodnotou je voliteľná.
5. **Nahraďte ručné deklarácie:** použite enumy s hodnotami (backed enum) pre povolené hodnoty a vstavané validačné obmedzenia tam, kde zodpovedajú existujúcim kontrolám.
6. **Spracujte vnorené dáta:** vytvorte vnorené DTO a pridajte PHPDoc `list<ItemInput>` pre polia DTO.
7. **Presuňte len validáciu kontraktu:** databázové vyhľadávania a obchodné pravidlá nechajte v službách alebo handleri; pre deterministické pravidlá vstupu použite vlastné validačné obmedzenia.
8. **Prepnite základnú triedu:** nahraďte `handle(array $params)` za `handleDto(Input $input)` a odstráňte ručné pretypovania/prístupy k poľu.
9. **Porovnajte správanie:** spustite úspešné aj chybné požiadavky, skontrolujte cesty polí, porovnajte OpenAPI a skontrolujte formulár konzoly a nahrávanie.
10. **Skontrolujte kompatibilitu:** prísnejšiu konverziu zdôraznite vo svojom changelogu. Ak musí ostať súčasné správanie, ponechajte pre endpoint `params()`.

Prepis DTO na `params()` ide rovnako opačným smerom: deklarujte jeden `InputParam` pre každú property s rovnakým názvom, príznakom povinnosti, predvolenou hodnotou a povolenými hodnotami, validačné obmedzenia presuňte do handlera alebo vlastného `InputParam` a znova porovnajte operáciu OpenAPI.

