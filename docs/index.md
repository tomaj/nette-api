---
layout: home
title: Nette API – REST API library for Nette
titleTemplate: false
description: 'Robust REST APIs inside your Nette application: versioned endpoints, authorization, validated params and outputs, CORS, rate limiting, logging and OpenAPI docs.'
hero:
  name: Nette API
  text: Robust REST APIs inside your Nette application
  tagline: 'Add a complete API layer to Nette: versioned endpoints, authorization, validated params and outputs, CORS, rate limiting, logging and OpenAPI docs – wired through Nette DI, one handler class per endpoint.'
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: Examples
      link: /examples/
    - theme: alt
      text: GitHub
      link: https://github.com/tomaj/nette-api
features:
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><rect class="na-soft" x="18" y="5" width="26" height="38" rx="7"/><path class="na-ink" d="M27 16h10M27 24h10M27 32h6"/><path class="na-acc" d="M3 24h15M11 16.5l7.5 7.5-7.5 7.5"/></svg>'
    title: Handlers, not presenters
    details: Every endpoint is a small class with params(), outputs() and handle(). ApiDecider maps method, version and package to it.
    link: /guide/handlers
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><rect class="na-soft" x="3" y="5" width="33" height="13" rx="4"/><rect class="na-soft" x="3" y="23" width="24" height="13" rx="4"/><path class="na-ink" d="M9 11.5h12M9 29.5h8"/><circle class="na-accf na-badge" cx="35" cy="35" r="10.5"/><path class="na-mark" d="M30 35.3l3.4 3.4 6.4-7.2"/></svg>'
    title: Validated input
    details: GET, POST, PUT, cookie, file, raw and JSON body params with types, allowed values and JSON schemas. Wrong input answers 400.
    link: /guide/params
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><path class="na-soft" d="M21 3l17 6v12c0 10.8-7 18.8-17 23C11 40 4 31.8 4 21V9z"/><circle class="na-ink-f" cx="21" cy="18.5" r="4.5"/><rect class="na-ink-f" x="19" y="20" width="4" height="10" rx="2"/><circle class="na-accf na-badge" cx="36" cy="36" r="10"/><path class="na-mark" d="M30.5 39a5.5 5.5 0 0 1 11 0M36 39l3-3.6"/><circle class="na-mark-f" cx="36" cy="39" r="1.7"/></svg>'
    title: Authorization and limits
    details: Bearer tokens, API keys in a header, query or cookie, IP restrictions, and your own rate limit per endpoint.
    link: /guide/authorization
  - icon: '<svg class="na-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false"><rect class="na-soft" x="3" y="3" width="27" height="35" rx="6"/><path class="na-ink" d="M12.5 11c-2.8 0-2.8 1.8-2.8 4s-1 3.2-2.2 3.2c1.2 0 2.2 1 2.2 3.2s0 4 2.8 4M20.5 11c2.8 0 2.8 1.8 2.8 4s1 3.2 2.2 3.2c-1.2 0-2.2 1-2.2 3.2s0 4-2.8 4"/><rect class="na-accf na-badge" x="17" y="22" width="28" height="23" rx="6"/><path class="na-mark" d="M23 29.5l4.5 4-4.5 4M30.5 38.5h7.5"/></svg>'
    title: OpenAPI and console
    details: OpenApiHandler turns the handlers into an OpenAPI 3 document. ApiConsoleControl gives you a form to try every endpoint.
    link: /guide/openapi
---

## What it looks like

<Diagram name="architecture-1" alt="A request goes through ApiDecider, the rate limit, authorization and param error reporting to the handler; the output is checked against its schema and logged, and the response (200 with JSON, XML or another format) goes back to the client; 429, 401 and 400 errors go back to the client too, and an unknown URL becomes a 404 from DefaultHandler, which is logged" />

A handler is one class:

<<< @/../examples/hello-world/app/Api/HelloHandler.php

and a few lines of configuration register it as `GET /api/v1/hello`:

<<< @/../examples/hello-world/config/common.neon{yaml}

The [hello world example](/examples/hello-world) runs exactly this code in CI.

## Where to go next

<LinkCards :items="[
  { title: 'Getting started', text: 'Install the library, add a route and your first endpoint.', link: '/guide/getting-started' },
  { title: 'Architecture', text: 'What happens to a request, step by step.', link: '/guide/architecture' },
  { title: 'Examples', text: 'Thirteen runnable projects, tested on every push.', link: '/examples/' },
  { title: 'Reference', text: 'Every class and interface of version 3.x.', link: '/api/reference' },
]" />

## FAQ

### What is Nette API?

A library that adds a REST API layer to a Nette application: endpoints, validated params and outputs, authorization, CORS, rate limits, logging and an OpenAPI document. See [Getting started](/guide/getting-started).

### How does it fit into a Nette application?

Through one route to its `ApiPresenter` and services registered in your neon config, so handlers get their dependencies from Nette DI like any other service. See [Getting started](/guide/getting-started).

### Which versions of PHP and Nette does Nette API 3.x need?

PHP 8.3 or newer, `nette/application` and `nette/http` 3.x, and Latte 3. The full list is in [Getting started](/guide/getting-started#requirements).

### Do I have to use presenters for my endpoints?

No. The library ships its own `ApiPresenter`, and your code lives in handlers. See [Handlers](/guide/handlers).

### How do API versions work?

The version is part of every endpoint, so `v1` and `v2` of the same URL can point to different handlers. See [Links and versions](/guide/links) and the [versions example](/examples/versions).

### How do I protect an endpoint?

Give it an authorization when you register it: bearer token, API key in a header, query or cookie, or your own class. See [Authorization](/guide/authorization).

### Does it slow down every request?

Handlers registered by class name are created from the DI container only when their endpoint is called. The exception is the OpenAPI, listing and console endpoints: they read every endpoint through `getApis()`, which creates all handlers. See [Architecture](/guide/architecture).

### Can I generate API documentation from my code?

Yes. Register `OpenApiHandler` and it describes every endpoint as OpenAPI 3.0, in JSON or YAML. See [OpenAPI](/guide/openapi).

### Is the documentation available in Slovak?

Yes, every page has a Slovak version. Use the language menu at the top, or start at the [Slovak home page](/sk/).

### How do I upgrade to 4.0?

There is no 4.0 yet; 3.x is the current line. A [4.0.0 milestone](https://github.com/tomaj/nette-api/milestone/9) is open with planned breaking changes, such as removing the unsafe `'auto'` CORS mode and raising the minimum PHP version. It has no release date yet. The [upgrade guide](/upgrade) covers older major versions.
