---
title: 'Example: OpenAPI and console'
description: 'Runnable Nette API example that publishes an OpenAPI 3.0 document in JSON and YAML, a JSON endpoint listing, and the web console with the {apiLink} Latte tag.'
---

# Example: OpenAPI and console

A small bookshelf API documented three ways: `GET /api/v1/openapi`, `GET /api/v1/endpoints` and a web console at `/console`.

<ProjectSource name="openapi-console" :files="['config/common.neon', 'app/Api/BookListHandler.php', 'app/UI/Console/ConsolePresenter.php', 'app/UI/Console/default.latte', 'app/UI/Console/show.latte', 'e2e.sh']" />

<Diagram name="examples-openapi-console-1" alt="OpenApiHandler, ApiListingHandler and the console presenter all read the endpoints from ApiDecider" />

## The services

<<< @/../examples/openapi-console/config/common.neon{yaml}

## A documented handler

<<< @/../examples/openapi-console/app/Api/BookListHandler.php

Summary, description, tags, params and outputs all end up in the OpenAPI document.

## The console

<<< @/../examples/openapi-console/app/UI/Console/ConsolePresenter.php

The `default` view (`default.latte`) renders the endpoint list. Clicking an endpoint redirects to `actionShow`, which renders `show.latte` with the console form for that one endpoint.

<<< @/../examples/openapi-console/app/UI/Console/default.latte

<<< @/../examples/openapi-console/app/UI/Console/show.latte

## The test

<<< @/../examples/openapi-console/e2e.sh{bash}

## FAQ

### Why is YAML available?

The example requires `symfony/yaml`. Without it only `?format=json` is allowed. See [OpenAPI](/guide/openapi).

### How does the document know POST /books needs a token?

It reads `BearerTokenAuthorization` and adds the `Bearer` security scheme.

### Should the console be public?

No, protect it or leave it out of production. See [Web console](/guide/console).

### What does {apiLink} need?

The Latte extension and the `apiLink` filter, both in the config above. See [Links](/guide/links#the-apilink-tag).

### Why does the test keep a cookie jar?

Nette forms check the `_nss` same-site cookie before accepting a POST.
