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

`TypedHandler` turns the DTO declaration into `InputParam` objects, so validation, OpenAPI and the console work as with `params()`.

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

Attributes are in `Tomaj\NetteApi\Input` and target promoted constructor properties. Each promoted property has exactly one source attribute. The external name defaults to the property name; pass a name to override it. `description` and `examples` (an array keyed by the example name, as in `InputParam::addExample()`) become the description and examples of the generated `InputParam`.

| Attribute | Source | Generated parameter type |
| --- | --- | --- |
| `#[Query]` | query string | `InputParam::TYPE_GET`, OpenAPI `in: query` |
| `#[Path]` | matched endpoint path | `InputParam::TYPE_PATH`, OpenAPI `in: path` |
| `#[Header]` | HTTP header | `InputParam::TYPE_HEADER`, OpenAPI `in: header` |
| `#[Body]` | a field in JSON, form or multipart body | `InputParam::TYPE_BODY`, part of `requestBody` |
| `#[File]` | uploaded file | `InputParam::TYPE_FILE`, multipart `requestBody` |

Every root DTO property becomes one `MappedInputParam`, an `InputParam` that validates and maps through the DTO metadata. Code that inspects `params()` checks `getType()`, not a concrete class.

For example, `#[Header('X-Request-Id')] public ?string $requestId = null` reads `X-Request-Id`, not `requestId`. Header lookup is case-insensitive. A `#[Path]` name starts with a letter or an underscore and has letters, digits and underscores, because it is written as a `{name}` segment; a `#[Header]` name uses dashes, not underscores (`X-Request-Id`, not `X_Request_Id`), because HTTP header names use dashes. `Accept`, `Content-Type` and `Authorization` are not `#[Header]` names, because OpenAPI does not describe them as parameters: the body media types come from `#[Consumes]` and credentials from the authorization of the endpoint. Anything else is a compile-time error with the corrected name. A path or header value is one string, so a native list or nested DTO there is a compile-time error; split it with a caster, for example `#[Header('X-Tags')] #[Cast(Split::class, ',')] public array $tags`.

`PathInputParam`, `HeaderInputParam` and `BodyInputParam` are also public for handlers that use `params()`, see [Input params](/guide/params#path-header-and-body-params).

### Path values and URL templates

A path value is declared in the endpoint URL with `{name}`, for example `users/{id}/detail`. It is required, it cannot be nullable or have a default, and its OpenAPI path placeholder has the same name. A `{name}` without a `#[Path]` property, or a `#[Path]` property missing from the URL, fails the container compilation with the DI extension; with `addApi()` it throws a `LogicException` when the endpoint is first used. The same check covers a `PathInputParam` of a `params()` handler. Static endpoint URLs work unchanged. A path value is one URL segment, so it cannot contain a slash: a request with an encoded slash (`%2F`) in the path matches no template.

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

The route has to let the template segments through. The route of the [typed input example](/examples/typed-input), `/api/v<version>/<package>[/<apiAction>][/<params>]`, lets one segment through after the action; a deeper template such as `{id}/orders/{orderId}` needs `[/<params .+>]` in the route mask, otherwise Nette answers `404` before the API sees the request. A request is looked up among the static endpoints first and then among the templates. When the URL has segments after the action, a template that matches the whole path wins over a shorter static endpoint: `/users/7/detail` goes to `users/{id}/detail`, not to a static `users/7`. Two templates that can match the same URL fail the container compilation with the DI extension, and `addApi()` throws a `LogicException` that names both. Make a static segment differ.

### Required values and defaults

A non-nullable property without a default is required. A nullable property or a property with a default is optional. A missing nullable value becomes `null`; a missing value with a default uses that default. This also sets `required` in OpenAPI.

An optional body field accepts an omitted field, while an explicit JSON `null` is accepted only for a nullable property. An empty string is not silently converted to `null`. A default is `null`, a scalar, an array or a backed enum case; an object default such as `= new Inner()` is a compile-time error. Use `= null` and create the object in the handler.

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

Errors use the same envelope as `params()` handlers. The detail key is the field path: dots enter nested DTOs and brackets identify list items. Top-level sources use their external names.

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

When error details are disabled, only `status` and `message` are returned. With Problem Details enabled, the same field paths and messages appear in its `errors` list as `parameter` and `detail`.

Errors of the body as a whole, such as malformed JSON (`Syntax error`), a charset other than UTF-8 or an unreadable body, appear under `body`, so no field can be read as `body`: compiling such a DTO fails, give the property another input name, for example `#[Body('payload')]`.

A missing nested object reports the nested object path. Unknown input fields are ignored, as with ordinary query and form params.

## OpenAPI and the API console

The [web console](/guide/console) builds its form from the generated `InputParam` objects and sends each value to its source; there is no DTO-specific mode.

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

JSON schema is generated here only as an OpenAPI output format. Runtime validation uses the recursive DTO mapper and never requires a JSON schema.

## Security notes

- **Mapping runs before authorization.** The input is converted and validated before the authorization is checked, and only the answer waits for it: a client without access gets `401`, not field errors. The rate limit still runs first. A caster or a constraint is therefore reachable without a login, so keep them pure and cheap: no database, network or file access. Put `#[Length(max: ...)]` on every list.
- **Cap the request body at the web server.** `#[Length(max: ...)]` limits a list before its items are mapped, but the body is decoded first, and a decoded list takes many times more memory in PHP than in the request. Limit the size where the request enters: `client_max_body_size` in nginx, `LimitRequestBody` in Apache, and `post_max_size` and `memory_limit` in PHP.
- **`#[Header]` is not proof of origin.** Do not use it for a header that a proxy sets or strips to carry trust, such as `X-Forwarded-User`. PHP and web servers map `X_Request_Id` and `X-Request-Id` to the same name, so a client can send a look-alike. Authenticate such values in the proxy and drop the client's copy there.
- **Form bodies and cookies.** A DTO with body fields accepts URL-encoded forms by default, which any website can submit without a CORS preflight. For an endpoint authenticated by a cookie or by the client address, use `#[Consumes('application/json')]`, see [body, files and content types](/guide/typed-input-body#forms-and-cookie-authenticated-endpoints).

## Compiled metadata and workers

With the DI extension the DTO metadata is compiled into the container, and changing a DTO, a caster, a constraint or an enum rebuilds it. A constant of another class used in an attribute argument or a default is not tracked; clear the cache after changing it. Without the extension the metadata is built once per process.

Every read of a typed handler's `params()` parses the current request again, and the path values are set by `ApiDecider` for each request, so workers (RoadRunner, FrankenPHP, Swoole) are fine as long as each handles one request at a time; concurrent coroutines in one process are not supported.

## Example

A runnable project with every source attribute, a nested body and a file upload is described in [Typed input example](/examples/typed-input).
