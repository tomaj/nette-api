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

The DTO mapper creates `AddressInput` and both `ItemInput` objects recursively. An array of DTOs needs PHPDoc such as `list<ItemInput>`, and a scalar array `list<string>`, `list<int>`, `list<float>` or `list<bool>` (`T[]`, `@phpstan-param` and `@psalm-param` work too; `non-empty-list<T>` is read as `list<T>`, so add `#[Length(min: 1)]` to require items); associative arrays, unions, `mixed`, `object` and untyped arrays fail when the container is compiled.

The same DTO works with URL-encoded form fields, and with multipart fields when it has a `#[File]` property or `#[Consumes('multipart/form-data')]`. Nested fields use PHP bracket notation, such as `address[street]` and `items[0][sku]`; `deliveryAt` keeps the same RFC 3339 string. Body parsing depends on the content type, not on a JSON schema.

OpenAPI documents this body as (responses omitted):

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

The endpoint accepts a form too, but OpenAPI 3.0 cannot describe a list of objects in a form body, so only JSON is listed here. A DTO that also has a `#[File]` keeps `multipart/form-data`, because a file cannot be sent otherwise; its multipart schema lists such a field by its plain name and says in its description that it is sent in bracket notation (`items[0][sku]`). A DTO whose body has only scalars, lists of scalars and objects of scalars lists both media types; the form schema names a list `tags[]` and sends an object as `deepObject`. OpenAPI applies `deepObject` only to URL-encoded forms, so a multipart schema lists the fields of an object one by one (`address[street]`):

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

## Body content types

A `TypedHandler` with body fields accepts **`application/json`** and **`application/x-www-form-urlencoded`** by default, or **`application/json`** and **`multipart/form-data`** when its DTO contains a `#[File]` property; OpenAPI lists the same types in `requestBody.content` (only multipart when a file is required). The content type is matched without parameters. JSON must be UTF-8: a request that names another `charset` is a `400`. A body without a `Content-Type`, or with a type the handler does not accept, is a `415`; a request with no body at all passes when no body field is required, whatever its `Content-Type` says. File properties appear only in the `multipart/form-data` schema, and a JSON request to a DTO with a required file gets a `400` that says files need `multipart/form-data`.

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

An upload-only endpoint uses `#[Consumes('multipart/form-data')]`. A DTO with a `#[File]` property, required or optional, must list `multipart/form-data` in `#[Consumes]`; otherwise the container compilation fails.

A request with another content type is rejected before mapping with **415 Unsupported Media Type**. The 415 is sent after the authorization and the rate limits, so a client without access gets `401` instead. With error details enabled, the message states both the received type and the exact accepted types, for example:

```json
{
  "status": "error",
  "message": "unsupported media type",
  "detail": "Content-Type 'text/csv' is not supported for this endpoint. Use one of: application/json, application/x-www-form-urlencoded."
}
```

With Problem Details enabled, the 415 uses that format, and its `detail` always names the accepted types.

### JSON, form and multipart differences

| Concern | JSON | URL-encoded form / multipart |
| --- | --- | --- |
| Nested DTOs | objects: `{"address":{"street":"Main 1"}}` | bracket names: `address[street]=Main+1` |
| Lists of DTOs | arrays: `"items":[{"sku":"BOOK-1"}]` | indexed names: `items[0][sku]=BOOK-1` |
| Native scalar types | numbers and booleans remain JSON numbers/booleans | values arrive as strings and go through the same strict scalar conversion |
| Null | explicit `null` is allowed only for a nullable property | there is no null literal: omit the field to use null or its default |
| Empty string | an actual string, not null | an actual string, not null |
| Object vs list | `{}` is an object (its missing properties are reported one by one). `[]` is a list and is rejected wherever an object is required, including an empty list. A JSON object is rejected where a list is required | bracket names build an object (`address[street]`); indexed names build a list (`items[0]`) |
| Files | not supported | `multipart/form-data` only |

For example, JSON `"quantity": 2` stays an integer, while form `quantity=2` becomes an integer only after the complete string passes strict conversion. Neither accepts `2pcs`. JSON `false` and form `remember=0` are valid booleans; an arbitrary non-empty form string is not silently `true`.

For an optional form value, omit `deliveryAt` instead of sending `deliveryAt=null` or an empty value. A multipart file cannot be replaced with a JSON base64 string; an application needing base64 uploads can keep `BaseHandler` and parse its own raw body.

Send multipart with `POST`. Forms over `max_input_vars` are rejected with `400`. PHP does not keep a multipart body to count it again, so a multipart form with exactly `max_input_vars` fields is rejected too; raise the limit if your forms are that large.

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

`FileUpload` has the properties `originalName`, `clientContentType`, `temporaryPath`, `size` and `error` (a PHP `UPLOAD_ERR_*` code). `?FileUpload` is an optional upload and `list<FileUpload>` accepts several files in one field; file inputs of the list the client left empty are left out of it. The handler checks the upload error, the size and the real type before storing the file; nothing is moved automatically. `#[File]` is allowed only on the root input DTO of the handler, not inside a nested DTO or a list.

