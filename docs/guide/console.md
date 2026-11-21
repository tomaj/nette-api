---
title: Web console
description: Browse and call Nette API endpoints from the browser with ApiListingControl and ApiConsoleControl, generated forms for params and tokens.
---

# Web console

Two Nette components let you click through your API: `ApiListingControl` lists the endpoints and `ApiConsoleControl` renders a form for one, sends the request with cURL and shows the response.

::: danger Not for production
The console can call every endpoint with any token you type. Protect the presenter, or leave it out of production.
:::

## The presenter

<<< @/../examples/openapi-console/app/UI/Console/ConsolePresenter.php

The `default` view (`default.latte`) renders the endpoint list. Clicking an endpoint redirects to `actionShow`, which renders `show.latte` with the console form for that one endpoint.

<<< @/../examples/openapi-console/app/UI/Console/default.latte

<<< @/../examples/openapi-console/app/UI/Console/show.latte

## The components

| Component | Constructor | Notes |
| --- | --- | --- |
| `ApiListingControl` | `(ApiDecider)` | `onClick[]` gets `method, version, package, apiAction` |
| `ApiConsoleControl` | `(IRequest, EndpointInterface, ApiHandlerInterface, ApiAuthorizationInterface, ?ApiLink, ?ApiConsoleFormFactoryInterface)` | `setTemplateFilePath()`, `setFormRenderer()` |

The form has one field per param, a field for the token or API key (or user and password for Basic), and a timeout. Write your own `ApiConsoleFormFactoryInterface` to change it.

## What the console sends

The console builds the request from the params of the handler, whether they come from `params()` or from a typed DTO, so there is no DTO-specific mode.

| Param source | Sent as |
| --- | --- |
| query | the query string |
| path value | trimmed and URL-encoded into the `{name}` segment of the URL; an empty value, `.`, `..` or a value with a slash is not sent |
| header value | a request header |
| body field | the first media type the handler reads that the console can build: JSON, a URL-encoded form or multipart |
| file | a multipart upload; with a file attached the body fields go in the same multipart request |

In a JSON body each value keeps its declared type; a nested object, or an object item of a list, takes JSON text. Blank optional fields are not sent; a blank path value, `.` or `..` shows an error instead of sending.

## FAQ

### Why does the form submit fail with a CSRF error?

Nette forms accept a POST only from the same site, with the `_nss` cookie. Open the console in the browser, not through a script without cookies.

### Can the console call another server?

It calls the URL in the form, which defaults to the endpoint on the current host.

### How do I change the look?

Use `setTemplateFilePath()` with your own Latte template, or `setFormRenderer()`.

### Does the console show the request it sent?

Yes, the response panel shows the URL, the headers, the body and the time.

### Do I need the console if I have OpenAPI?

No, they are independent. [OpenAPI](/guide/openapi) works with any OpenAPI tool.
