---
title: 'Typed input: body, files and content types'
description: 'Body fields of a typed DTO in Nette API: nested objects and lists, JSON, form and multipart content types, Consumes, 415 and file uploads.'
---

# Typed input: body, files and content types

This page covers the body fields of a typed DTO: nested objects and lists, the JSON, form and multipart content types, `#[Consumes]` and file uploads. The basics are in [Typed input (DTO)](/guide/typed-input).

## POST with a nested DTO and an array

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

The DTO mapper creates `AddressInput` and both `ItemInput` objects recursively. `list<ItemInput>` is required PHPDoc for an array of DTOs; `list<string>`, `list<int>`, `list<float>` and `list<bool>` are supported for scalar arrays. The usual spellings are read: `list<T>`, `non-empty-list<T>`, `T[]`, with `|null` or a leading `?` (nullability comes from the PHP type), and `@phpstan-param` or `@psalm-param` instead of `@param`. A description after the type is fine (`@var list<string> Names of the things`). An item is one non-nullable type, so `list<?int>` and `list<int|string>` are compile-time errors; use an item DTO. Associative arrays, unions other than nullable types, `mixed`, `object` and untyped arrays are rejected when the container is compiled, not halfway through a request. A list default is checked item by item at the same time, so `@param list<int>` with the default `['x']` does not compile.

The same DTO works with URL-encoded form fields, and with multipart fields when it has a `#[File]` property or `#[Consumes('multipart/form-data')]`. Nested fields use PHP bracket notation, such as `address[street]` and `items[0][sku]`; `deliveryAt` keeps the same RFC 3339 string. Body parsing depends on the content type, not on a JSON schema.

## Body content types

A `TypedHandler` with body fields accepts both **`application/json`** and **`application/x-www-form-urlencoded`** by default. If its DTO contains a `#[File]` property, the defaults are **`application/json`** and **`multipart/form-data`**: multipart replaces URL-encoded form, because URL-encoded form cannot carry files, and files are uploaded together with the body fields. The content type is matched without parameters, so `application/json; charset=utf-8` is accepted. JSON is always read as UTF-8: another `charset`, such as `application/json; charset=ISO-8859-1`, is a `400` instead of being decoded wrongly (`utf-8`, its common spelling `utf8` and `us-ascii` are accepted). A body that arrives without any `Content-Type` is a `415` even when every field is optional, because it would otherwise be dropped silently.

OpenAPI advertises the same choices in `requestBody.content`: JSON and URL-encoded form, or JSON and multipart when a file is present. When a file is required, only multipart is advertised, because a JSON request could only be answered with `400`. File properties appear only in the `multipart/form-data` schema, because JSON cannot carry a file. A JSON request to a DTO with a required file gets `400` with the file field as the path and the message `File is required and can only be uploaded as multipart/form-data, but the request is application/json`. Body fields are `TYPE_BODY` params and files are `TYPE_FILE` params; there is still only one validation and mapping path.

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

An upload-only endpoint uses `#[Consumes('multipart/form-data')]`. Restricting to a form type without `multipart/form-data` means that the endpoint cannot receive files; declaring an impossible combination, such as a required `#[File]` with JSON-only consumption, is a container-compilation error.

A request with another content type is rejected before mapping with **415 Unsupported Media Type**. The 415 is sent after the authorization and the rate limits, so a client without access gets `401` instead, and it is logged by `ApiLoggerInterface` like a handled request. With error details enabled, the message states both the received type and the exact accepted types, for example:

```json
{
  "status": "error",
  "message": "unsupported media type",
  "detail": "Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded."
}
```

An absent body content type on a request that requires a body receives the same actionable 415 rather than a later null-property error. With Problem Details enabled, status 415 and the same message are used in that format, and its `detail` always names the accepted types. The default envelope hides `detail` when error details are disabled, as it does for field errors.

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

For an optional form value, omit `deliveryAt` instead of sending `deliveryAt=null` or an empty value. A multipart file cannot be replaced with a JSON base64 string; an application needing base64 uploads can keep `BaseHandler` and parse its own raw body.

PHP reads multipart bodies only for `POST`. A `multipart/form-data` body sent with `PUT` or `PATCH` is not parsed by PHP, so the endpoint answers `400` with `multipart/form-data is only parsed by PHP for POST requests, but this one is PUT. Send it with POST, or use application/json`. Send uploads and multipart forms with `POST`. A URL-encoded form with more fields than PHP's `max_input_vars` (1000 by default) is a `400` `Too many form fields`, for `POST` as well as for `PUT` and `PATCH`. PHP cuts a multipart `POST` that has more fields before the application sees it, and that cannot be told from a complete one: raise `max_input_vars` or send JSON for such forms.

## Forms and cookie-authenticated endpoints

A URL-encoded form is a "simple" cross-site request: a page on any website can submit it to your API together with the visitor's cookies and without a CORS preflight, which a JSON body cannot do. A DTO with body fields accepts a form by default, so an endpoint authenticated only by a session cookie, or by the address of the client, can be reached by such a cross-site form. Restrict it to JSON with `#[Consumes('application/json')]` and keep `SameSite` on the cookie. Endpoints authenticated by a bearer token or an API key header are not affected, because a browser form cannot set those headers.


## File upload

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

`FileUpload` is the library's typed upload value object. It exposes the original name, client content type, temporary path, size and upload error; it does not trust the client content type or move the file automatically. `?FileUpload` means an optional upload and `list<FileUpload>` accepts several files in one field. Check the upload error, size and real type before storing it, exactly as with `FileInputParam`. Upload errors such as `UPLOAD_ERR_INI_SIZE` are not turned into validation errors; the `FileUpload` carries them in `error`. Only a missing file is: no entry, or `UPLOAD_ERR_NO_FILE` with an empty name, and for `list<FileUpload>` every slot being `UPLOAD_ERR_NO_FILE`. Upload requests must use `POST`, see above. `#[File]` belongs on the input DTO of the handler; a `#[File]` inside a nested DTO or a list of DTOs is a compile-time error, because PHP keeps nested uploads in a separate `$_FILES` tree and body fields must never be read as upload metadata.

