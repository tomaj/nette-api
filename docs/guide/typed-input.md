---
title: Typed input (DTO)
description: 'Typed request DTOs in Nette API: Query, Path, Header, Body and File attributes, required values, errors, OpenAPI and the API console. The second, equal way to declare handler input next to params().'
---

# Typed input (DTO)

The classes on this page are part of Nette API 4.0. A runnable project is in [`examples/typed-input`](/examples/typed-input).

A typed input DTO keeps the request contract next to the values a handler uses. Attributes describe where each value comes from, property types describe its PHP type, and constraint attributes describe validation. The same declaration feeds validation, OpenAPI and the API console.

More on the DTO style: [body, files and content types](/guide/typed-input-body), [conversion, casts and constraints](/guide/typed-input-conversion).

## params() or a DTO

A handler declares its input in one of two equal ways. Both are fully supported, share registration, authorization, rate limits, outputs, OpenAPI and the console, and can be mixed freely in one application.

| | `params()` | Typed DTO |
| --- | --- | --- |
| Handler | `BaseHandler` with `params()` and `handle(array $params)` | `TypedHandler` with `handleDto(SomeInput $input)` |
| You declare | a list of `InputParam` objects | a readonly class with attributes on its constructor properties |
| The handler gets | an array of validated values | a typed object |
| Conversion | the `InputType` conversion of [Input params](/guide/params) | the strict conversion of [typed input](/guide/typed-input-conversion) |
| Nested bodies | `JsonInputParam` with a JSON schema | nested DTOs and lists of DTOs, validated recursively |
| A custom input source | any `InputParam` subclass | not available, there are five source attributes |

Pick `params()` when the endpoint relies on its looser conversion, on a custom `InputParam` or on manual parsing of an unusual body. Pick a DTO when you want typed values, nested bodies, cross-field constraints or the whole request contract in one class. Neither style is deprecated, and choosing one for an endpoint does not affect any other endpoint. [params() and DTO side by side](/guide/input-styles) shows the same endpoint written in both styles.

`TypedHandler` turns the DTO declaration into the same `InputParam` objects that `params()` returns.

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

| Attribute | Source | Generated parameter type |
| --- | --- | --- |
| `#[Query]` | query string | `InputParam::TYPE_GET`, OpenAPI `in: query` |
| `#[Path]` | matched endpoint path | `InputParam::TYPE_PATH`, OpenAPI `in: path` |
| `#[Header]` | HTTP header | `InputParam::TYPE_HEADER`, OpenAPI `in: header` |
| `#[Body]` | a field in JSON, form or multipart body | `InputParam::TYPE_BODY`, part of `requestBody` |
| `#[File]` | uploaded file | `InputParam::TYPE_FILE`, multipart `requestBody` |

Every DTO property becomes one `MappedInputParam`, an `InputParam` that validates and maps through the DTO metadata. Code that inspects `params()` checks `getType()`, not a concrete class.

For example, `#[Header('X-Request-Id')] public ?string $requestId = null` reads `X-Request-Id`, not `requestId`. Header lookup is case-insensitive. A `#[Path]` name starts with a letter or an underscore and has letters, digits and underscores, because it is written as a `{name}` segment; a `#[Header]` name uses dashes, not underscores (`X-Request-Id`, not `X_Request_Id`), because PHP exposes header names with dashes. Anything else is a compile-time error with the corrected name. A path or header value is one string, so a native list or nested DTO there is a compile-time error; split it with a caster, for example `#[Header('X-Tags')] #[Cast(Split::class, ',')] public array $tags`.

`PathInputParam` and `HeaderInputParam` are also public for handlers that use `params()`, see [Input params](/guide/params#path-header-and-body-params).

Do not use `#[Header]` for a header that a proxy sets or strips to carry trust, such as `X-Forwarded-User`. PHP and web servers map `X_Request_Id` and `X-Request-Id` to the same name, so a client can send a look-alike. Authenticate such values in the proxy and drop the client's copy there. The `Authorization` header is also read from `REDIRECT_HTTP_AUTHORIZATION`, which Apache with CGI or FastCGI sets after a rewrite such as `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`. `BearerTokenAuthorization` reads it the same way, so a token that reaches `#[Header('Authorization')]` also reaches the authorization. Other headers are read from the `HTTP_*` server variables.

### Path values and URL templates

A path value is declared in the endpoint URL with `{name}`, for example `users/{id}/detail`. It is required, it cannot be nullable or have a default, and its OpenAPI path placeholder has the same name. A missing or undeclared path parameter is a container-compilation error. Static endpoint URLs work unchanged.

Register the template as the action of the endpoint, in any of the usual ways:

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

The route has to let the extra segment through, as the one in the [typed input example](/examples/typed-input) does: `/api/v<version>/<package>[/<apiAction>][/<params>]`. A request is looked up among the static endpoints first and then among the templates. If two templates match one URL, the request fails with a `LogicException` that names both, so make a static segment differ.

### Required values and defaults

A non-nullable property without a default is required. A nullable property or a property with a default is optional. A missing nullable value becomes `null`; a missing value with a default uses that default. This also sets `required` in OpenAPI.

Required path parameters cannot be nullable or have a default. An optional body field accepts an omitted field, while an explicit JSON `null` is accepted only for a nullable property. An empty string is not silently converted to `null`. A default is `null`, a scalar, an array or a backed enum case; an object default such as `= new Inner()` is a compile-time error, because defaults are stored in the compiled container. Use `= null` and create the object in the handler.

## Complete request example

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


A request with a body, a nested DTO and a list is in [body, files and content types](/guide/typed-input-body#post-with-a-nested-dto-and-an-array).

## Error responses

The response envelope stays the same as `ParamsProcessor`. The detail key is the field path: dots enter nested DTOs and brackets identify list items. Top-level sources use their external names.

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

When error details are disabled, only `status` and `message` are returned. With Problem Details enabled, the same field paths and messages appear in its input-error extension. Malformed JSON and an unreadable body keep the existing body-parser errors.

A missing nested object reports the nested object path. Unknown input fields are ignored, matching ordinary query/form parameter handling; they are not silently mapped to properties.

## OpenAPI and the API console

`TypedHandler::params()` exposes generated `InputParam` objects, so the existing console automatically gets labels, required markers, defaults, examples, enum choices and file inputs. The console sends each value to its source: path values are URL-encoded into `{name}` segments, header values become request headers, and body values are sent in the first media type the handler reads that the console can build: JSON with the declared types (a nested DTO field takes JSON text, a list field has one input per item and a DTO item takes JSON text), a URL-encoded form or multipart. A file always means multipart, with bracket names for nested fields. There is no DTO-specific console mode, see [Web console](/guide/console).

For OpenAPI, query, path and header properties become operation parameters. Body properties are combined into one object schema; nested DTOs and lists become nested schemas. By default `requestBody.content` lists both `application/json` and `application/x-www-form-urlencoded`, or JSON and `multipart/form-data` when the DTO contains a file. `#[Consumes]` limits the list and accepted requests to explicit media types. An operation with a body also documents a `415` response. `nullable: true` appears only on body properties: a query string, a path segment or a header cannot carry `null`, so an optional query or header value is simply absent. A nullable enum lists `null` in its `enum`, as OpenAPI 3.0.3 requires.

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

### Long-running workers

The request values of a typed handler (body, files, path and header values) are kept in static state for the current request. `ApiPresenter::run()` resets it at the start of every request, so PHP-FPM, RoadRunner, FrankenPHP worker mode and Swoole all see only the current request as long as every request goes through `ApiPresenter` and a worker handles one request at a time. Concurrent requests in one process, such as interleaved Swoole coroutines, are not supported, because the state is process-wide. Code that calls a typed handler's `params()` outside the presenter in a worker, for example a test or a CLI command, calls `Tomaj\NetteApi\Input\RequestInput::reset()` and `Tomaj\NetteApi\Params\PathValues::reset()` first.


## Security notes

- **Mapping runs before authorization.** The input is converted and validated before the authorization is checked, and only the answer waits for it: a client without access gets `401`, not field errors. The rate limit still runs first. A caster or a constraint is therefore reachable without a login, so keep them pure and cheap: no database, network or file access. Put `#[Length(max: ...)]` on every list. One request reports at most 100 errors in total, however deeply lists and objects are nested; the rest of the body is not checked.
- **Cap the request body at the web server.** `#[Length(max: ...)]` limits a list before its items are mapped, but the body is decoded first, and a list of small objects takes far more memory in PHP than in the request (roughly 75 times for a list of numbers). Limit the size where the request enters: `client_max_body_size` in nginx, `LimitRequestBody` in Apache, and `post_max_size` and `memory_limit` in PHP.
- **`#[Header]` is not proof of origin.** See the note under [source attributes](#source-attributes).
- **Form bodies and cookies.** A DTO with body fields accepts URL-encoded forms by default, which any website can submit without a CORS preflight. For an endpoint authenticated by a cookie or by the client address, use `#[Consumes('application/json')]`, see [body, files and content types](/guide/typed-input-body#forms-and-cookie-authenticated-endpoints).

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


## How it is built

- **Compile-time metadata.** `NetteApiExtension` reflects DTOs and writes their `InputParam` definitions and mapping metadata into the compiled container. Requests and OpenAPI generation do not reflect DTOs again and need no cache invalidation setup; enum backing types are cached per class. Changing a DTO, its parent class, a trait it uses, a caster, a constraint or an enum rebuilds the container like changing endpoint attributes. Without the DI extension, a small generated-metadata registry is built once per request; no runtime Nette Cache dependency is introduced.
- **One metadata pipeline.** DTO metadata is translated into the existing `InputParam` objects, so validation, OpenAPI and the console have no separate DTO branches.
- **Recursive runtime validation.** It handles JSON, form and multipart consistently and gives exact property paths. A JSON schema is only generated for OpenAPI.
- **Readonly promoted properties only.** DTOs are instantiated once through their constructor and cannot be partially mutated by the mapper. A required constructor parameter that is not a promoted property, and variadic or by-reference parameters, are compile-time errors.
- **Strict, explicit scalar conversion.** Invalid client data is a `400`, not a surprising cast.
- **A small constraint set.** Range, Length, Pattern and Email cover common API contracts; custom constraints cover application rules without a validation framework.
- **JSON and form accepted by default, `Consumes` restricts.** One DTO serves JSON and form clients, and OpenAPI advertises the same media types; when a file is present, multipart replaces URL-encoded form, which cannot carry files. `Consumes` is a class-level attribute like the other endpoint attributes, and its name keeps it apart from the HTTP `Accept` header, which is about responses.
- **`handleDto(Input $input)` as the handler method.** The name states that it is the entry point of the handler, and reflection reads the DTO class from its parameter, so no extra attribute or configuration is needed.
- **`Cast` with `CasterInterface`.** Application formats stay local to a property, constructor arguments cover options such as `d.m.Y`, and `CastException` gives field-path 400 errors. An optional schema-hint interface keeps OpenAPI metadata optional for casters.

