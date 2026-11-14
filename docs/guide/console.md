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
| path value | URL-encoded into the `{name}` segment of the URL |
| header value | a request header |
| body field | the first media type the handler reads that the console can build: JSON, a URL-encoded form or multipart |
| file | a multipart upload; with a file attached the body fields go in the same multipart request |

In a JSON body each value keeps its declared type, and a nested object field takes JSON text, for example `{}` for an empty object. A list field has one input per item, and an item that is an object takes JSON text. A typed field that is left blank is not sent (an empty string would never be a valid number, date, choice, object or list item), so an unused list slot is skipped. A blank optional text field is not sent either, so the server uses its default; a required text field is sent as an empty string, so that its length and pattern constraints can be tried. An empty header is never sent. A blank path value is not sent at all: the console shows `The path value 'id' is empty. Fill it in and send the request again.` instead of calling a URL with an empty segment. A number that JSON cannot hold, such as `1e999` or an integer beyond the 64-bit range, is sent as text, so the server answers with its normal `400`.

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
