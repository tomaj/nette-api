---
title: 'Example: hello world'
description: 'The smallest runnable Nette API project - one GET endpoint with an optional name parameter, the router, the services and an end-to-end test.'
---

# Example: hello world

One endpoint, `GET /api/v1/hello?name=Ada`, answering `{"message":"Hello Ada!"}`.

<ProjectSource name="hello-world" :files="['app/Api/HelloHandler.php', 'app/RouterFactory.php', 'config/common.neon', 'e2e.sh']" />

<Diagram name="examples-hello-world-1" alt="curl calls GET /api/v1/hello?name=Ada, the router passes it to ApiPresenter, HelloHandler answers 200 with the message" />

## The handler

<<< @/../examples/hello-world/app/Api/HelloHandler.php

`name` is optional with the default `world`, so `GET /api/v1/hello` answers `{"message":"Hello world!"}`.

## The route

<<< @/../examples/hello-world/app/RouterFactory.php

## The services

<<< @/../examples/hello-world/config/common.neon{yaml}

## The test

<<< @/../examples/hello-world/e2e.sh{bash}

## FAQ

### Why does POST /api/v1/hello answer 404?

The endpoint is registered for `GET` only. The method is part of the endpoint, see [Handlers](/guide/handlers#registering-a-handler).

### Why DebuggerConfigurator?

It shows error details and validates outputs when Tracy is in development mode. See [Outputs](/guide/outputs#when-outputs-are-validated).

### Where does the JSON come from?

`JsonApiResponse` encodes the array and sets `Content-Type: application/json`. See [Outputs and responses](/guide/outputs#responses).

### Can I add a second endpoint?

Add another `addApi()` line with a new `EndpointIdentifier`. The [params example](/examples/params-validation) has two.

### What does each step of the setup do?

[Getting started](/guide/getting-started) explains the same files step by step.
