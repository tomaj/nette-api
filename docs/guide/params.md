---
title: Input params
description: Declare and validate input in Nette API - GET, POST, PUT, cookie, file, raw and JSON body params, types, required values, allowed values, multi values and examples.
---

# Input params

`params()` returns the inputs of the endpoint. `ApiPresenter` reads and validates all of them before `handle()` runs, and `handle()` gets the values keyed by name.

<Diagram name="params-1" alt="Input is read by InputParam::getValue, converted to the declared type and validated; errors answer 400, valid values reach the handler" />

## Param classes

| Class | Reads from | Notes |
| --- | --- | --- |
| `GetInputParam` | query string | supports types and `setMulti()` |
| `PostInputParam` | form body, `$_POST` | supports types and `setMulti()` |
| `PutInputParam` | form-encoded body of any method | no type conversion, but a type is still checked: `INTEGER` rejects even `"12"`, so use `STRING` or no type |
| `CookieInputParam` | cookie | no type conversion, but a type is still checked: `INTEGER` rejects even `"12"`, so use `STRING` or no type |
| `FileInputParam` | uploaded file, `$_FILES` | `setMulti()` for several files |
| `RawInputParam` | the whole request body as a string | `setMulti()` throws |
| `JsonInputParam` | the whole request body, decoded as JSON | needs a JSON schema; `setMulti()` throws |

The second constructor argument of the first two is the type, one of the `Tomaj\NetteApi\Validation\InputType` constants:

```php
new GetInputParam('limit', InputType::INTEGER);
```

## Settings

All params share a fluent API:

| Method | Effect |
| --- | --- |
| `setRequired()` | `null` or `''` answers `Field is required`. |
| `setDefault($value)` | used when the input is missing |
| `setAvailableValues([...])` | only these values pass, else `Field contains not available value(s)`. Keys of an associative array are the values, the array values are labels for the console. |
| `setMulti()` | expects an array, `?id[]=1&id[]=2`; types and allowed values apply to every item |
| `setDescription()` | shown in OpenAPI and the console |
| `addExample($name, $value)` / `setExample($value)` | shown in OpenAPI and prefilled in the console |

## Types

| Type | Conversion |
| --- | --- |
| `INTEGER`, `DOUBLE`, `FLOAT` | numeric strings are converted |
| `BOOLEAN` | `1`, `true` (any case) are `true`, everything else is `false` |
| `STRING` | kept as it is |
| `ARRAY` | must be an array |

::: warning A value that is not a number becomes null
In 3.x `?limit=abc` with `INTEGER` is not an error: the value is converted to `null` before validation, so an optional param passes and a required one answers `Field is required`. Always handle `null` in `handle()`. The [params example](/examples/params-validation) tests this.
:::

## JSON body

`JsonInputParam` takes a JSON schema as a string and validates the decoded body against it:

<<< @/../examples/params-validation/app/Api/UserCreateHandler.php{35-37}

Broken JSON answers `400` with the parser message, a missing required body answers `missing data`.

## The 400 response

With error details on (see [Error handling](/guide/errors)), the errors are listed per param:

```json
{"status":"error","message":"wrong input","detail":{"status":["Field contains not available value(s)"]}}
```

With details off, the response has only `status` and `message`.

## FAQ

### How do I read a JSON body without a schema?

Use `RawInputParam` and decode it yourself, or pass a permissive schema such as `{"type":"object"}` to `JsonInputParam`.

### Why is my integer param null instead of 400?

Non-numeric values become `null` in 3.x, see the warning above. Make the param required or check for `null`.

### Can a param take several values?

Yes, `setMulti()`, then send `name[]=a&name[]=b`. See the [params example](/examples/params-validation).

### Where do the examples I add show up?

In the OpenAPI document and as prefilled values in the [web console](/guide/console).

### Can authorization read the params?

Not the parsed values. `authorized()` takes no arguments; read the value from the HTTP request yourself, as `QueryApiKeyAuthentication` does. Params are read before authorization but only the error handler gets them, see [ApiPresenter pipeline](/guide/presenter).
