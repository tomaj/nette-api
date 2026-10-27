---
title: OpenAPI
description: Publish an OpenAPI 3.0 document of your Nette API with OpenApiHandler - JSON or YAML, security schemes, error responses, initData and allowedEndpoints.
---

# OpenAPI

`OpenApiHandler` is a normal handler that returns an OpenAPI 3.0 document of all endpoints with the same version as its own. Register it like any endpoint.

<Diagram name="openapi-1" alt="OpenApiHandler reads params, outputs, metadata and authorization of every endpoint from ApiDecider and returns JSON or YAML" />

<<< @/../examples/openapi-console/config/common.neon{yaml}

`OpenApiHandler` needs `ApiDecider`, which in turn holds the handlers, so register it by class name in `addApi()`. It is created only when its endpoint is called.

## What goes into the document

| From | Into |
| --- | --- |
| `summary()`, `description()`, `tags()`, `deprecated()` | the operation |
| `params()` | `parameters`, and `requestBody` for body params such as JSON and raw input |
| `outputs()` | `responses` with JSON schemas |
| the authorization class | `securitySchemes` and `security`: `Bearer`, `Basic`, or `apiKey` in query, header or cookie |
| params, the authorization | `400` when there are params, `401` unless `NoAuthorization`, and `500` always |

Endpoints with your own authorization class appear without `security`.

## Constructor

```php
new OpenApiHandler(
    ApiDecider $apiDecider,
    ApiLink $apiLink,
    Nette\Http\Request $request,
    array $initData = [],        // merged into the document, e.g. info.title
    array $allowedEndpoints = [], // path pattern => methods to include; empty means all
    ?SpecBuilder $specBuilder = null // custom builder, see below
)
```

`?format=yaml` returns YAML when `symfony/yaml` is installed; otherwise only `json` is allowed.

## allowedEndpoints

Keys (or values in a list) are patterns matched case-insensitively against the endpoint path without version (e.g. `users/list`); values limit HTTP methods, e.g. `['users' => ['get', 'post'], 'orders/list' => 'get']`.

::: warning
A pattern matches anywhere in the path, so `users` also includes `admin/users-internal`, and `*` is not a wildcard. Use patterns that are unique enough and check the generated document when it is public. See [#211](https://github.com/tomaj/nette-api/issues/211).
:::

## Builders

The document is created by small builders in `Tomaj\NetteApi\OpenApi`, each responsible for one part:

| Class | Creates |
|---|---|
| `SpecBuilder` | whole document: info, servers, components, paths (filters APIs by version) |
| `PathBuilder` | paths and operations (summary, tags, parameters, request body, security, responses) |
| `ParameterBuilder` | query and cookie parameters |
| `RequestBodyBuilder` | JSON, raw, form and multipart request bodies |
| `ResponseBuilder` | error responses and their schemas, responses from handler outputs |
| `SecuritySchemeBuilder` | security schemes and requirements from authorizations (override `createScheme()`) |

`SchemaRegistry` (transforms JSON schemas and collects `definitions` into `components/schemas`) and `EndpointFilter` (`allowedEndpoints`) are helpers created for each generated document.

### Replacing a builder via DI

Extend the builder and register it in DI together with `PathBuilder` and `SpecBuilder`. `OpenApiHandler` gets `SpecBuilder` autowired, otherwise the default builders are used. Links are always created by the `ApiLink` of the handler.

```php
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\OpenApi\ParameterBuilder;

class MyParameterBuilder extends ParameterBuilder
{
    public function build(ApiHandlerInterface $handler): array
    {
        $parameters = parent::build($handler);
        $parameters[] = ['name' => 'X-Request-Id', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string']];
        return $parameters;
    }
}
```

```neon
services:
	- MyParameterBuilder
	- Tomaj\NetteApi\OpenApi\PathBuilder
	- Tomaj\NetteApi\OpenApi\SpecBuilder
```

## FAQ

### How do I change the title "Nette API"?

Pass `initData: [info: [title: 'My API']]`. It is merged recursively over the generated document.

### Can I hide internal endpoints?

Yes, list the public ones in `allowedEndpoints` (mind [the matching rules](#allowedendpoints)), or register a second `OpenApiHandler` for a different version.

### Why are endpoints of version 2 missing?

The document covers only the version of the `OpenApiHandler` endpoint. Register one per version; see [Links and versions](/guide/links#versions).

### Can I use the document with Swagger UI?

Yes, point Swagger UI at the URL of the endpoint. The library also has its own [web console](/guide/console).

### Is the OpenAPI endpoint public?

Only if you register it with `NoAuthorization`, as the [OpenAPI example](/examples/openapi-console) does. Any [authorization](/guide/authorization) works.
