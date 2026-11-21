---
title: 'Typed input: conversion, casts and constraints'
description: 'How a typed DTO in Nette API converts values strictly, runs custom casters and validates with Range, Length, Pattern, Email and custom constraints.'
---

# Typed input: conversion, casts and constraints

This page covers what happens to a value between the request and the DTO property: strict conversion, `#[Cast]` and the constraint attributes. The basics are in [Typed input (DTO)](/guide/typed-input).

## Strict conversion

Typed input never relies on PHP's loose scalar casts.

| Target | Accepted input |
| --- | --- |
| `string` | a string that is valid UTF-8; a query, form, header or path value with other bytes is a `400` `Value must be valid UTF-8` |
| `int` | an integer, or a string matching an optional sign and digits only, within PHP integer range |
| `float` | an integer/float, or a complete decimal or scientific numeric string; NaN and infinity are rejected |
| `bool` | boolean `true`/`false`, integer `1`/`0`, string `'true'`/`'false'`/`'1'`/`'0'` (case-insensitive) |
| backed enum | exactly one of its backing values; int-backed enums use the integer conversion rules |
| `DateTimeImmutable` | an RFC 3339 string with date, time and explicit offset, for example `2026-11-06T14:30:00+01:00` (`T` and `Z` may be lowercase; a leap second such as `23:59:60` is rejected, because PHP cannot represent it) |
| `array` | a list whose items each satisfy the PHPDoc item type |

The UTF-8 check applies to typed input only; a `params()` handler keeps accepting any bytes.

`?page=1a` for an integer is a `400`, never `1` or `null`. Whitespace is not trimmed and unknown strings do not become `false`. JSON numbers and booleans keep their types, so `1.0` or `1e2` is not an integer.

A query list uses the PHP bracket form `ids[]=3&ids[]=4` (OpenAPI names the parameter `ids[]`); a comma-separated value uses `Split`. A query carries only scalars, enums and lists of them; read nested objects from the body with `#[Body]`.

DTO mapping supports only the five sources above; an input from another source uses `params()` with a custom `InputParam`.

## Constraints

Constraint attributes are in `Tomaj\NetteApi\Validation` and can be combined on one property. They run after successful conversion on the converted value; a constraint on a list validates its length, not its scalar items.

| Attribute | Applies to | Meaning |
| --- | --- | --- |
| `#[Range(min: 1, max: 100)]` | `int`, `float` | inclusive numeric bounds; either bound may be omitted |
| `#[Length(min: 1, max: 80)]` | `string`, `array` | Unicode character count or list item count; either bound may be omitted; a list over `max` is rejected before its items are mapped |
| `#[Pattern('/^[A-Z]+$/')]` | `string` | complete PCRE match (`D` modifier added); an invalid pattern fails compilation. OpenAPI gets `pattern` when the regex is portable to JavaScript, otherwise `x-pattern` |
| `#[Email]` | `string` | valid email address |

Validation collects all field errors rather than stopping at the first one. One request reports at most 100 errors, nested lists included; when something was left unchecked, a note under the key `body` says so. Messages state the actual limits, for example `Value must have between 1 and 50 items` instead of a generic `Invalid value`.

### Custom constraints

A custom constraint implements `ConstraintInterface` and is also a PHP attribute:

```php
interface ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string;
}
```

Return `null` for success or one message for failure. `ConstraintContext` exposes the property path, the DTO class and `value('sibling')` for other converted properties of the same DTO. Do not throw for invalid client input. Custom constraints have no OpenAPI representation unless the application extends the OpenAPI builders.

### Constraints on list items

A constraint on a list validates the list, so there are no per-item constraints for `list<int>` or `list<string>`. To check every item, write a custom constraint that loops over the list, or use a list of small item DTOs, which validates each item and reports it as `items[1].quantity`.

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

Casters are in `Tomaj\NetteApi\Input`, including `DateFormat`, `UnixTimestamp` and `Split`. The caster runs **instead of** the built-in conversion for that property. It runs first, returns the property's declared value, and only then do `Range`, `Length`, `Pattern`, `Email` and custom constraints run on that cast value.

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

Any other exception from a caster, or any exception from the constructor of a nested DTO, is a server error, not a validation error: the error handler answers it with `500` after the authorization. Container compilation checks that the caster implements `CasterInterface`, accepts the attribute arguments and declares a native `cast()` return type matching the property type (`array` with list PHPDoc for `list<T>`; `mixed` is not accepted).

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
| `#[Cast(UnixTimestamp::class)]` | `1798185600` or `'1798185600'` | `DateTimeImmutable` from seconds since Unix epoch |
| `#[Cast(Split::class, ',')]` | `'red,green,blue'` | `list<string>` (always strings: a `list<int>` property needs a caster of its own); no implicit trimming or removal of empty items |

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

The `DateFormat('d.m.Y')` schema hint is `{type: string, example: '24.12.2026'}`; only `DateFormat('Y-m-d')` adds `format: date`.

### CSV to list&lt;string&gt;

```php
final readonly class ReportInput
{
    /** @param list<string> $columns */
    public function __construct(
        #[Query]
        #[Cast(Split::class, ',')]
        public array $columns = [],
    ) {
    }
}
```

In OpenAPI the property is one string and the console shows one text input. `?columns=id,createdAt,total` becomes `['id', 'createdAt', 'total']`; an empty string gives `['']`, which a constraint can reject.

### Decimal string to a Money value object

A custom caster class can construct an application value object. The following example intentionally handles a comma decimal separator without using a floating-point conversion:

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

`12,50` becomes `Money`, and constraints receive that object. `12.50`, `1a` and an array produce a 400 at `price` with the caster's message. Because the caster replaces the built-in conversion, `Money` can have a private constructor and a static factory.

