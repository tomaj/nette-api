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
    array $allowedEndpoints = [] // path => methods to include, * in the path; empty means all
)
```

`?format=yaml` returns YAML when `symfony/yaml` is installed; otherwise only `json` is allowed.

## FAQ

### How do I change the title "Nette API"?

Pass `initData: [info: [title: 'My API']]`. It is merged recursively over the generated document.

### Can I hide internal endpoints?

Yes, list the public ones in `allowedEndpoints`, or register a second `OpenApiHandler` for a different version.

### Why are endpoints of version 2 missing?

The document covers only the version of the `OpenApiHandler` endpoint. Register one per version; see [Links and versions](/guide/links#versions).

### Can I use the document with Swagger UI?

Yes, point Swagger UI at the URL of the endpoint. The library also has its own [web console](/guide/console).

### Is the OpenAPI endpoint public?

Only if you register it with `NoAuthorization`, as the [OpenAPI example](/examples/openapi-console) does. Any [authorization](/guide/authorization) works.
