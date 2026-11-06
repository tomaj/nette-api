---
title: Typed input (DTO)
description: 'Typed request DTOs in Nette API: Query, Path, Header, Body and File attributes, strict conversion, nested validation, OpenAPI and migration from params().'
---

# Typed input (DTO)

The classes on this page are part of Nette API 4.0. A runnable project is in [`examples/typed-input`](/examples/typed-input).

A typed input DTO keeps the request contract next to the values a handler uses. Attributes describe where each value comes from, property types describe its PHP type, and constraint attributes describe validation. The same declaration feeds validation, OpenAPI and the API console.

`TypedHandler` turns the DTO declaration into the existing `InputParam` objects. `BaseHandler::params()` and `handle(array $params)` remain fully supported.

## The smallest handler

Extend `TypedHandler` and declare exactly one non-nullable DTO parameter on `handleDto()`. The class of the `handleDto()` parameter is the input DTO; it does not need to be repeated in an attribute or configuration.

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

`GET /api/v1/users/list?page=2&status=active` produces a `ListUsersInput` with an integer `page` and a `UserStatus` enum. `TypedHandler` implements `handle(array $params)` as final; application code writes only `handleDto()`.

Constructor dependencies, `summary()`, `description()`, `outputs()`, tags and registration work as for `BaseHandler`. Endpoint and authorization attributes are independent of input attributes and can be used together.

## Source attributes

Attributes are in `Tomaj\NetteApi\Input` and target promoted constructor properties. Each promoted property has exactly one source attribute. The external name defaults to the property name; pass a name to override it. `description` and repeatable `examples` become the description and examples of the generated `InputParam`.

| Attribute | Source | Generated parameter |
| --- | --- | --- |
| `#[Query]` | query string | `GetInputParam` |
| `#[Path]` | matched endpoint path | `PathInputParam` |
| `#[Header]` | HTTP header | `HeaderInputParam` |
| `#[Body]` | a field in JSON, form or multipart body | `BodyInputParam` |
| `#[File]` | uploaded file | `FileInputParam` |

For example, `#[Header('X-Request-Id')] public ?string $requestId = null` reads `X-Request-Id`, not `requestId`. Header lookup is case-insensitive.

`PathInputParam` and `HeaderInputParam` are also public for handlers that use `params()` directly. `ParamType` gains `Path` and `Header`; `ParamsProcessor` reads both sources and OpenAPI emits them as `in: path` and `in: header`.

```php
public function params(): array
{
    return [
        (new PathInputParam('id', InputType::INTEGER))->setRequired(),
        new HeaderInputParam('X-Request-Id', InputType::STRING),
    ];
}
```

A path parameter is declared in the endpoint URL with `{name}`, for example `users/{id}/detail`. It is required and its OpenAPI path placeholder has the same name. A missing or undeclared path parameter is a container-compilation error. Endpoint URL templates are the small routing addition needed by this design; static endpoint URLs continue to work unchanged.

### Required values and defaults

A non-nullable property without a default is required. A nullable property or a property with a default is optional. A missing nullable value becomes `null`; a missing value with a default uses that default. This also sets `required` in OpenAPI.

Required path parameters cannot be nullable or have a default. An optional body field accepts an omitted field, while an explicit JSON `null` is accepted only for a nullable property. An empty string is not silently converted to `null`.

## Complete request examples

### GET with query, path and header

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

Request:

```http
GET /api/v1/users/42/detail?limit=10&status=active HTTP/1.1
X-Request-Id: req-01J8Y7N6R9
```

The handler receives `id: 42`, `limit: 10`, `status: UserStatus::Active` and the request ID string. No casts or array-key lookups are needed.

### POST with a nested DTO and an array

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

JSON request body:

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

The DTO mapper creates `AddressInput` and both `ItemInput` objects recursively. `list<ItemInput>` is required PHPDoc for an array of DTOs; `list<string>`, `list<int>`, `list<float>` and `list<bool>` are supported for scalar arrays. Associative arrays, unions other than nullable types, `mixed`, `object` and untyped arrays are rejected when the container is compiled, not halfway through a request.

The same DTO works with form and multipart fields. Nested fields use PHP bracket notation, such as `address[street]` and `items[0][sku]`; `deliveryAt` keeps the same RFC 3339 string. Body parsing depends on the content type, not on a JSON schema.

## Body content types

A `TypedHandler` with body fields accepts both **`application/json`** and **`application/x-www-form-urlencoded`** by default. If its DTO contains a `#[File]` property, it accepts **`multipart/form-data`** as well, so files can be uploaded together with the body fields. The content type is matched without parameters, so `application/json; charset=utf-8` is accepted.

OpenAPI advertises the same choices in `requestBody.content`: JSON and URL-encoded form, or JSON and multipart when a file is present. `BodyInputParam` represents the common fields while `FileInputParam` keeps the existing upload behavior; there is still only one validation and mapping path.

### Restrict accepted types with Consumes

Use the class-level `#[Consumes]` attribute when an endpoint deliberately supports only one representation. It accepts one or more media types:

```php
#[Consumes('application/json')]
final class CreateOrderHandler extends TypedHandler
{
    public function handleDto(CreateOrderInput $input): ResponseInterface
    {
        // JSON only
    }
}
```

```php
#[Consumes('application/json', 'application/x-www-form-urlencoded')]
final class ImportHandler extends TypedHandler
{
    // Explicitly the same two types as the default for a DTO without files.
}
```

A multipart-only upload endpoint uses `#[Consumes('multipart/form-data')]`. Restricting to a form type without `multipart/form-data` means that the endpoint cannot receive files; declaring an impossible combination, such as a required `#[File]` with JSON-only consumption, is a container-compilation error.

A request with another content type is rejected before mapping with **415 Unsupported Media Type**. With error details enabled, the message states both the received type and the exact accepted types, for example:

```json
{
  "status": "error",
  "message": "unsupported media type",
  "detail": "Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded."
}
```

An absent body content type on a request that requires a body receives the same actionable 415 rather than a later null-property error. With Problem Details enabled, status 415 and the same message are used in that format.

### JSON, form and multipart differences

| Concern | JSON | URL-encoded form / multipart |
| --- | --- | --- |
| Nested DTOs | objects: `{"address":{"street":"Main 1"}}` | bracket names: `address[street]=Main+1` |
| Lists of DTOs | arrays: `"items":[{"sku":"BOOK-1"}]` | indexed names: `items[0][sku]=BOOK-1` |
| Native scalar types | numbers and booleans remain JSON numbers/booleans | values arrive as strings and go through the same strict scalar conversion |
| Null | explicit `null` is allowed only for a nullable property | there is no null literal: omit the field to use null or its default |
| Empty string | an actual string, not null | an actual string, not null |
| Object vs list | `{}` is an object, including when its properties are missing. `[]` is a list and is rejected wherever an object is required, including an empty list. A JSON object is rejected where a list is required | bracket names build an object (`address[street]`); indexed names build a list (`items[0]`) |
| Files | not supported | `multipart/form-data` only |

For example, JSON `"quantity": 2` stays an integer, while form `quantity=2` becomes an integer only after the complete string passes strict conversion. Neither accepts `2pcs`. JSON `false` and form `remember=0` are valid booleans; an arbitrary non-empty form string is not silently `true`.

For an optional form value, omit `deliveryAt` instead of sending `deliveryAt=null` or an empty value. A multipart file cannot be replaced with a JSON base64 string under this design; an application needing base64 uploads can keep `BaseHandler` and parse its own raw body.

## Custom conversions with Cast

Use `#[Cast]` when a field has an application-specific textual representation. It names a caster class and optionally passes constructor arguments:

```php
interface CasterInterface
{
    public function cast(mixed $value): mixed;
}

final class CastException extends RuntimeException
{
}
```

Casters are in `Tomaj\NetteApi\Input`, including `DateFormat`, `UnixTimestamp` and `Split`. The caster runs **instead of** the built-in conversion for that property. It runs first, returns the property's declared value, and only then do `Range`, `Length`, `Pattern`, `Email` and custom constraints run on that cast value. Nested paths still work exactly as elsewhere.

A caster reports invalid client input by throwing `Tomaj\NetteApi\Input\CastException` with a clear message. It becomes a 400 in the normal error envelope under the property path:

```json
{
  "status": "error",
  "message": "wrong input",
  "detail": {
    "issuedOn": ["Date must match d.m.Y, for example 24.12.2026"]
  }
}
```

Other exceptions are programmer/server errors and are not disguised as client validation errors. Container compilation verifies that the caster class exists, is instantiable, implements `CasterInterface`, accepts the declared attribute arguments and has a native `cast()` return type identical to the property type (or a documented covariant class subtype). A `list<T>` caster must declare `array` with matching list-item PHPDoc. This check is limited to native declarations; `mixed` is not accepted as a caster return type. An incompatible caster fails during compilation, not on the first request.

### OpenAPI schema hints

A caster can optionally implement `OpenApiCasterInterface` alongside `CasterInterface`:

```php
interface OpenApiCasterInterface
{
    /** @return array{type?: string, format?: string, example?: mixed} */
    public function openApiSchema(): array;
}
```

The returned `type`, `format` and `example` describe the **wire value**, not the resulting PHP object. Without this optional interface, OpenAPI uses a string schema and the DTO description/example can document the format. A caster never changes runtime validation through its schema hint.

### Built-in casters and a Czech date

`DateFormat`, `UnixTimestamp` and `Split` cover the common non-default representations:

| Caster | Example input | Result |
| --- | --- | --- |
| `#[Cast(DateFormat::class, 'd.m.Y')]` | `'24.12.2026'` | `DateTimeImmutable` at midnight, rejecting impossible dates and trailing input |
| `#[Cast(UnixTimestamp::class)]` | `'1798185600'` | `DateTimeImmutable` from seconds since Unix epoch |
| `#[Cast(Split::class, ',')]` | `'red,green,blue'` | `list<string>`; no implicit trimming or removal of empty items |

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

The `DateFormat` schema hint is `{type: string, format: date, example: '24.12.2026'}`; `format` is a hint, not a claim that the wire value is RFC 3339.

### CSV to list&lt;string&gt;

```php
final readonly class ReportInput
{
    /** @var list<string> */
    #[Query]
    #[Cast(Split::class, ',')]
    public array $columns;
}
```

`?columns=id,createdAt,total` becomes `['id', 'createdAt', 'total']`. `Split` returns an empty list for an omitted optional property only through the property's normal default; an explicitly present empty string produces `['']`, which a constraint can reject.

### Decimal string to a Money value object

A custom caster class can construct an application value object: The following example intentionally handles a comma decimal separator without using a floating-point conversion:

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

`12,50` becomes `Money` and a subsequent custom `PositiveMoney` constraint receives that `Money` object. `12.50`, `1a` and an array produce a 400 at `price` with the caster's message. Currency-specific formatting remains in the application caster rather than becoming another global scalar rule.

### File upload

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

`FileUpload` is the library's typed upload value object. It exposes the original name, client content type, temporary path, size and upload error; it does not trust the client content type or move the file automatically. `?FileUpload` means an optional upload and `list<FileUpload>` accepts several files in one field. Check the upload error, size and real type before storing it, exactly as with `FileInputParam`.

## Strict conversion

Typed input never relies on PHP's loose scalar casts.

| Target | Accepted input |
| --- | --- |
| `string` | a string |
| `int` | an integer, or a string matching an optional sign and digits only, within PHP integer range |
| `float` | an integer/float, or a complete decimal or scientific numeric string; NaN and infinity are rejected |
| `bool` | boolean `true`/`false`, integer `1`/`0`, string `'true'`/`'false'`/`'1'`/`'0'` (case-insensitive) |
| backed enum | exactly one of its backing values; int-backed enums use the integer conversion rules |
| `DateTimeImmutable` | an RFC 3339 string with date, time and explicit offset, for example `2026-11-06T14:30:00+01:00` |
| `array` | a list whose items each satisfy the PHPDoc item type |

`?page=1a` for an integer is a `400`, never `1` or `null`. Whitespace is not trimmed implicitly and unknown strings do not become `false`. JSON numbers and booleans retain their types. Application normalization belongs in the handler or in a custom constraint, not in surprising global casts.

A custom source can still use `BaseHandler` and `params()`; DTO mapping supports only the five sources above.

## Constraints

Constraint attributes are in `Tomaj\NetteApi\Validation` and can be combined on one property. They run after successful conversion on the converted value; a constraint on a list validates its length, not its scalar items.

| Attribute | Applies to | Meaning |
| --- | --- | --- |
| `#[Range(min: 1, max: 100)]` | `int`, `float` | inclusive numeric bounds; either bound may be omitted |
| `#[Length(min: 1, max: 80)]` | `string`, `array` | Unicode character count or list item count; either bound may be omitted |
| `#[Pattern('/^[A-Z]+$/')]` | `string` | complete PCRE match; an invalid pattern is a compile-time error |
| `#[Email]` | `string` | valid email address |

Validation collects all field errors rather than stopping at the first one. Messages name the rejected constraint and its actual limits, for example `Length must be between 1 and 50 items` instead of a generic `Invalid value`.

### Custom constraints

A custom constraint implements `ConstraintInterface` and is also a PHP attribute:

```php
interface ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string;
}
```

Return `null` for success or one developer-facing message for failure. `ConstraintContext` exposes the property path and the DTO class; constraints must not throw for ordinary invalid client input. Custom constraints have no OpenAPI representation unless the application extends the OpenAPI builders.

## Error responses

The response envelope stays the same as `ParamsProcessor`. The detail key is the field path: dots enter nested DTOs and brackets identify list items. Top-level sources use their external names.

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

When error details are disabled, only `status` and `message` are returned. With Problem Details enabled, the same field paths and messages appear in its input-error extension. Malformed JSON and an unreadable body keep the existing body-parser errors.

A missing nested object reports the nested object path. Unknown input fields are ignored, matching ordinary query/form parameter handling; they are not silently mapped to properties.

## OpenAPI and the API console

`TypedHandler::params()` exposes generated `InputParam` objects, so the existing console automatically gets labels, required markers, defaults, examples, enum choices and file inputs. There is no DTO-specific console mode.

For OpenAPI, query, path and header properties become operation parameters. Body properties are combined into one object schema; nested DTOs and lists become nested schemas. By default `requestBody.content` lists both `application/json` and `application/x-www-form-urlencoded`, or JSON and `multipart/form-data` when the DTO contains a file. `#[Consumes]` limits the list and accepted requests to explicit media types.

The GET example above contributes this operation fragment (responses and unrelated fields omitted):

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

The POST example contributes:

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

JSON schema is generated here only as an OpenAPI output format. Runtime validation uses the recursive DTO mapper and never requires a JSON schema.

## Old and new handlers work side by side

**Yes: `params()` with `handle(array $params)` and `TypedHandler` with a DTO are two supported ways to write handlers in the same application.** Neither disables the other. Registration, authorization, outputs, rate limits, OpenAPI and the console are shared.

Migration is deliberately incremental. You can convert one endpoint, leave every other handler unchanged, and convert the next endpoint when it is convenient. There is no application-wide switch and no need for a big-bang migration.

## Migrating a handler from params() to a DTO

The following pairs show the same request contract before and after migration. Framework imports are omitted where they are unchanged; application constructor dependencies and response creation remain in the handler.

### 1. Simple GET: optional/defaulted query params and an enum

Before, the handler declares each query parameter, casts the values and maps the status string to an enum:

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

After, defaults, optionality and allowed enum values live in the DTO. `Range` replaces the manual bounds check and `handleDto()` receives values ready to use:

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

This also demonstrates the general rule for optional and defaulted values: `?UserStatus $status = null` is optional and becomes `null`; `int $limit = 20` is optional and becomes `20` when omitted. A required value would be non-nullable and have no default, for example `#[Query] public string $search`.

### 2. GET: a path id and a request header

Before, the endpoint URL contains `users/{id}/detail` and `params()` declares the two new public parameter classes:

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

After, `Path` and `Header` preserve the external names while the handler uses meaningful typed properties:

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

The endpoint registration and URL do not change. The DTO declaration still emits `id` as a required path parameter and `X-Request-Id` as an optional header in OpenAPI.

### 3. POST JSON: a nested object and an array

Before, a `JsonInputParam` contains a handwritten schema. The handler reads nested arrays and constructs its own values:

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

After, the nested structure is expressed once as DTO classes. No JSON schema is needed for runtime validation:

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

`list<OrderItemInput>` is important: it tells both the mapper and OpenAPI what an array item contains. A failure in the second quantity is reported under `items[1].quantity`.

### 4. Form POST

Before, two `PostInputParam` objects describe a URL-encoded login form:

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

After, `Body` means a body field regardless of whether the request is JSON or form-encoded:

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

The form still sends `email=ada%40example.com&password=...&remember=1`. The DTO mapper rejects unknown boolean strings rather than treating every non-empty string as `true`.

### 5. File upload

Before, the handler receives the raw upload entry and validates its upload error and size:

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

After, `FileUpload` gives typed access to the same metadata. Application checks for real file type and storage remain explicit:

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

The multipart field names remain `displayName` and `avatar`. Migrating to a DTO does not replace application security checks or PHP upload limits.

### 6. Custom validation

Before, validation that depends on several values lives in `handle()`:

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

After, simple conversion and RFC 3339 parsing are declarative. A custom constraint owns the cross-field rule, keeping the transport response consistent:

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

The design adds `ConstraintContext::value()` for reading sibling values in the same DTO so cross-property constraints do not have to inspect the HTTP request. Business rules that need a database still belong in application services; a constraint should remain a deterministic request-contract check.

### 7. Keep a handler unchanged when its behavior is intentional

Not every handler must migrate. A legacy endpoint may depend on the old `InputType::INTEGER` behavior, a custom `InputParam`, or manual parsing of an unusual content type. Leaving it on `BaseHandler` is valid:

```php
final class LegacyReportHandler extends BaseHandler
{
    public function params(): array
    {
        return [new GetInputParam('offset', InputType::INTEGER)];
    }

    public function handle(array $params): ResponseInterface
    {
        // Existing endpoint behavior intentionally stays unchanged.
        return $this->reports->generate($params['offset']);
    }
}
```

Its neighboring `ListReportsHandler` can already extend `TypedHandler`. This mixed state is supported indefinitely; migration is an ergonomic choice, not a requirement to change every endpoint.

### Migration checklist

1. **Capture the current contract:** save endpoint tests and the current OpenAPI operation, including defaults and error behavior.
2. **Create a readonly DTO:** one promoted property per query, path, header, body or file input.
3. **Preserve external names:** use explicit attribute names such as `#[Header('X-Request-Id')]` where the property name differs.
4. **Match requiredness:** non-nullable without a default means required; nullable or defaulted means optional.
5. **Replace manual declarations:** use backed enums for allowed values and the built-in constraints where they match existing checks.
6. **Handle nested data:** create a nested DTO and add `list<ItemInput>` PHPDoc for DTO arrays.
7. **Move only contract validation:** keep database lookups and business rules in services or the handler; use custom constraints for deterministic input rules.
8. **Switch the base class:** replace `handle(array $params)` with `handleDto(Input $input)` and remove manual casts/array lookups.
9. **Compare behavior:** run success and failure requests, inspect field paths, compare OpenAPI and check the console form and upload behavior.
10. **Review compatibility:** call out deliberately stricter conversion in the changelog or migration notes. Leave the endpoint unchanged if the old behavior must remain.

## Example

`examples/typed-input` follows the same standalone Composer layout and `e2e.sh` CI convention as the other examples (`public/index.php`):

```text
examples/typed-input/
├── composer.json
├── README.md
├── e2e.sh
├── config/common.neon
├── public/index.php
└── app/Api/
    ├── ListUsersHandler.php       # query, backed enum and defaults
    ├── UserDetailHandler.php      # path id and X-Request-Id header
    ├── CreateOrderHandler.php     # JSON/form body, nested DTO and list<ItemInput>
    ├── AvatarUploadHandler.php    # multipart body and FileUpload
    └── Input/                     # readonly DTOs and UserStatus
```

The end-to-end test covers successful JSON, form and multipart requests, defaults, enum conversion, every source attribute, strict rejection of `1a`, nested error paths and the generated OpenAPI document. CI runs it with the other examples.

## Design decisions

- **Compile-time metadata.** `NetteApiExtension` reflects DTOs and writes their `InputParam` definitions and mapping metadata into the compiled container. Requests and OpenAPI generation do no repeated reflection and need no cache invalidation setup. Changing a DTO rebuilds the container like changing endpoint attributes. Without the DI extension, a small generated-metadata registry is built once per request; no runtime Nette Cache dependency is introduced.
- **One metadata pipeline.** DTO metadata is translated into existing `InputParam` objects, rather than adding separate DTO branches to validation, OpenAPI and the console.
- **Recursive runtime validation.** It handles JSON, form and multipart consistently and gives exact property paths. JSON schema is only generated for OpenAPI.
- **Readonly promoted properties only.** DTOs are instantiated once through their constructor and cannot be partially mutated by the mapper.
- **Strict, explicit scalar conversion.** Invalid client data is a `400`, not a surprising cast.
- **Small extensible constraint set.** Range, Length, Pattern and Email cover common API contracts; custom constraints cover application rules without pulling in a validation framework.
- **JSON and form accepted by default, with `Consumes` for restrictions.** One DTO serves JSON and HTML/PHP-style form clients, and OpenAPI advertises the same media types; multipart is enabled when a file is present. JSON-only by default would be simpler but would contradict the requirement that a body need not be JSON. An endpoint method argument such as `accepts:` was rejected because it would separate the transport contract from the other class-level endpoint attributes. `Consumes` was chosen over `Accepts` because the latter is easily confused with the HTTP `Accept` response-negotiation header.
- **`handleDto(Input $input)` as the typed handler method.** It is explicit and avoids the magic-method feel of `__invoke`, while reflection still reads the DTO class from its parameter. `process` was considered but does not communicate that this is the handler entry point; an additional `#[Handler]` attribute was rejected because the method name is already unambiguous and an attribute would add no information.
- **`Cast` attributes with `CasterInterface`.** Application formats stay local to a property, constructor arguments cover options such as `d.m.Y`, and `CastException` supplies field-path 400 errors. `Convert` and `Process` were considered but are less precise: conversion can mean any mapping stage and processing does not necessarily produce a typed value. Symfony Serializer/normalizers were rejected as a larger second object-mapping system. An optional schema-hint interface was preferred over mandatory OpenAPI metadata on every caster.
