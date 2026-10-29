---
title: FAQ
description: Answers to common Nette API questions - 404 for registered endpoints, 500 for valid responses, tokens, CORS, logging, OpenAPI and upgrading.
---

# FAQ

## Why does my registered endpoint answer 404?

The method, version, package and action of the request must match an `EndpointIdentifier` exactly, and the route must lead to `Api:Api:default`. See [Getting started](/guide/getting-started).

## My handler returns the right data, but the client gets 500. Why?

The response did not match any declared output, and output validation is on. Look at `detail`, or read [Outputs](/guide/outputs#when-outputs-are-validated).

## Why does production say only "wrong input"?

Error details are off in production, also for `400`. See [Error handling](/guide/errors#error-details).

## How do I protect an endpoint with a token?

Use `BearerTokenAuthorization` or an API key class with a token repository. See [Authorization](/guide/authorization).

## Why does my browser app get a CORS error?

Set the allowed origin and enable preflight. See [CORS](/guide/cors).

## Why are 401 and 429 responses not in my log?

The presenter returns before the logger in 3.x. See [Logging](/guide/logging#what-is-logged).

## Should I use Problem Details?

Yes, in a new project: RFC 9457 errors have one machine-readable shape. They are not the default, because existing clients read `message`. See [Problem Details](/guide/problem-details).

## How do I publish OpenAPI?

Register `OpenApiHandler` as an endpoint. See [OpenAPI](/guide/openapi).

## How do I upgrade from 2.x?

Follow the [upgrade guide](/upgrade) and the [changelog](/changelog).

## Is there a complete project I can run?

Yes, 13 of them, tested in CI: see [Examples](/examples/).
