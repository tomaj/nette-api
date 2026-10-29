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

## Can I configure the API without a list of addApi() calls?

Yes, since 4.0.0 with the [DI extension](/guide/di-extension): one `api:` neon section with the endpoints, authorizations, rate limits, CORS and the error format, checked when the container is compiled.

## Can I declare an endpoint on the handler class?

Yes, since 4.0.0: put `#[Get(1, 'users')]` (or `Post`, `Put`, `Patch`, `Delete`, `Endpoint`) on the class and the DI extension registers it. See [Attribute endpoints](/guide/attributes).

## The container does not compile after I changed the api: section. What now?

Read the whole message. The [DI extension](/guide/di-extension) checks the configuration and the [endpoint attributes](/guide/attributes) when the container is compiled, and each message says what is wrong, where (the neon key, or the class and the attribute) and how to fix it, often with an example or a "Did you mean" suggestion. For example, `version: v1` fails with `the 'v' is added to the URL automatically (/v1/users). Use for example version: 1.` Settings that would silently do nothing also fail, such as an attribute on a method instead of the class, a `cors: mode` next to `allowedOrigins`, or a quoted `'@service'` reference.

## How do I publish OpenAPI?

Register `OpenApiHandler` as an endpoint. See [OpenAPI](/guide/openapi).

## How do I upgrade from 2.x?

Follow the [upgrade guide](/upgrade) and the [changelog](/changelog).

## Is there a complete project I can run?

Yes, 13 of them, tested in CI: see [Examples](/examples/).
