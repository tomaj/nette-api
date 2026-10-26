---
title: Outputs and responses
description: Return JSON, text, XML or redirects from Nette API handlers, declare outputs() with JSON schemas, and choose when they are validated.
---

# Outputs and responses

`handle()` returns a response object. `outputs()` describes the responses the endpoint may return, so they can be validated and documented.

## Responses

| Class | Constructor | Sends |
| --- | --- | --- |
| `JsonApiResponse` | `(int $code, array\|JsonSerializable $payload, string $contentType = 'application/json', string $charset = 'utf-8', $expiration = null)` | JSON with `Content-Length` |
| `TextApiResponse` | `(int $code, $data)` | the string as it is, or renders a Latte template; no content type is set |
| `XmlApiResponse` | `(int $code, string $data, $expiration = null)` | an XML string as `text/xml` with `Content-Length` |
| `RedirectResponse` | `(string $url, int $httpCode = 302)` | a redirect |

The status code you pass is the status code the client gets.

## XML, text and other formats

JSON is the usual choice, but a handler can return any format:

```php
use Nette\Http\IResponse;
use Tomaj\NetteApi\Response\TextApiResponse;
use Tomaj\NetteApi\Response\XmlApiResponse;

// XML, sent as text/xml with Content-Length
return new XmlApiResponse(IResponse::S200_OK, '<?xml version="1.0"?><user><id>7</id><name>Ann</name></user>');

// Plain text or a Latte template, echoed as it is
return new TextApiResponse(IResponse::S200_OK, "id,name\n7,Ann\n");
```

- `XmlApiResponse` takes a ready XML string; build it with `SimpleXMLElement`, `DOMDocument` or a template.
- `TextApiResponse` sets no content type, so the default applies (PHP `default_mimetype`, usually `text/html`). For CSV, YAML or files set it in your own class. The built-in `OpenApiHandler` uses `TextApiResponse` for its YAML output.
- For any other format write a class implementing `ResponseInterface`: a Nette `IResponse` with `getCode()`.
- Only `JsonApiResponse` and `RedirectResponse` have an output class. With validation on, an endpoint whose `outputs()` is not empty must return one of those, so leave `outputs()` empty on XML or text endpoints.

## Outputs

`outputs()` returns `OutputInterface` objects:

| Class | Matches |
| --- | --- |
| `JsonOutput(int $code, string $schema, string $description = '')` | a `JsonApiResponse` with this code whose payload matches the JSON schema |
| `RedirectOutput(int $code, string $description = '')` | a `RedirectResponse` with this code |

Both accept `addExample($name, $value)` for OpenAPI. The [params example](/examples/params-validation) keeps its schemas in JSON files:

<<< @/../examples/params-validation/app/Api/schema/user.json

## When outputs are validated

<Diagram name="outputs-1" alt="If validateSchema() is on, the response must match an output with the same code; otherwise handleSchema answers 500" />

`ApiPresenter` asks the output configurator `validateSchema()`. When it is true, the response must match at least one output. If none matches, the error handler's `handleSchema()` answers `500 Internal server error`. A handler with no outputs at all is never checked.

| Configurator | Validates and shows error details when |
| --- | --- |
| `DebuggerConfigurator` | Tracy is not in production mode |
| `EnvConfigurator($envVariable = 'APP_ENV', $productionValue = 'production')` | the variable is not the production value |
| `QueryConfigurator($request, 'schema_validate', 'error_detail')` | validates with `?schema_validate=1`, shows details with `?error_detail=1` (anything but `0` and `false`) |

::: tip
Validation costs time on every request. The usual setup checks outputs in development and CI, and skips it in production. The [logging example](/examples/logging-errors) shows both modes.
:::

## FAQ

### My response is right but I get 500 Internal server error. Why?

The output check failed. Look at `detail` in the response (error details on) or at the Tracy log. Every response code you return needs its own output. See [Error handling](/guide/errors).

### Do I have to declare outputs?

No. Without outputs nothing is validated, but [OpenAPI](/guide/openapi) has no response schema to show.

### Can I return my error responses without declaring them?

If the endpoint has outputs, a `404` you return must match one too, or validation fails. Add a `JsonOutput(404, ...)`.

### How do I send a file or HTML?

`TextApiResponse` echoes any string or renders a template. For files, write a small class implementing `ResponseInterface`: a Nette `IResponse` with `getCode()`.

### Can I use Fractal transformers?

Yes. `BaseHandler` creates a Fractal `Manager`, available as `$this->getFractal()`, and accepts an optional `ScopeFactoryInterface` in the constructor. See [Handlers](/guide/handlers#the-methods).
