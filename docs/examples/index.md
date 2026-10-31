---
title: Examples
description: 'Sixteen runnable Nette API projects tested in CI: params, JSON body, uploads, pagination, authorization, rate limit, CORS, versions, OpenAPI, logging, testing, DI extension, attribute endpoints.'
---

# Examples

Each example is a small Nette application in [`examples/`](https://github.com/tomaj/nette-api/tree/master/examples). Its `e2e.sh` starts the PHP built-in server and checks every response with curl; CI runs all of them against the current code of the library.

```bash
git clone https://github.com/tomaj/nette-api.git
cd nette-api/examples/hello-world
composer install
./e2e.sh
```

## Library version

The examples require `tomaj/nette-api` `^3.6`, so `composer install` gets the latest 3.x release. Some of them use features added in 3.6.0. [Attributes](/examples/attributes), [DI extension](/examples/di-extension), [Logging and errors](/examples/logging-errors) and [Problem Details](/examples/problem-details) need 4.0.0, for example the default log masking.

To run an example against your local checkout, as CI does, use `examples/ci/example.sh hello-world` from the repository root.

<LinkCards :items="[
  { title: 'Hello world', text: 'One GET endpoint, the smallest working setup.', link: '/examples/hello-world' },
  { title: 'Params and validation', text: 'Query params, allowed values, multi values and a JSON body with a schema.', link: '/examples/params-validation' },
  { title: 'Authorization and rate limit', text: 'Bearer token, API key, HTTP Basic and 3 requests per minute.', link: '/examples/auth' },
  { title: 'CORS', text: 'A fixed origin and preflight for a browser frontend.', link: '/examples/cors' },
  { title: 'OpenAPI and console', text: 'OpenAPI JSON and YAML, endpoint listing, web console, {apiLink}.', link: '/examples/openapi-console' },
  { title: 'Logging and errors', text: 'Request log with masked headers and query parameters (needs 4.0.0), own error handler, production mode.', link: '/examples/logging-errors' },
  { title: 'Problem Details', text: 'RFC 9457 errors for 400, 401, 404, 409, 429 and 500, Accept negotiation, OpenAPI schema (needs 4.0.0).', link: '/examples/problem-details' },
  { title: 'DI extension', text: 'The whole API in one api: neon section, default token, rate limit, CORS (needs 4.0.0).', link: '/examples/di-extension' },
  { title: 'Attribute endpoints', text: '#[Get], #[Post], #[Authorization] and #[RateLimit] on the handler classes, found by the DI extension (needs 4.0.0).', link: '/examples/attributes' },
  { title: 'Custom authorization', text: 'Your own ApiAuthorizationInterface with roles, the user in the handler, token or API key.', link: '/examples/custom-authorization' },
  { title: 'JSON and raw body', text: 'A JSON body checked by a schema, and a CSV body.', link: '/examples/json-body' },
  { title: 'File upload', text: 'One file and many files, size and real type checked.', link: '/examples/file-upload' },
  { title: 'Pagination', text: 'Typed page params, allowed values, links, an output for every code.', link: '/examples/pagination' },
  { title: 'API versions', text: 'v1 and v2 of one endpoint, deprecation headers, OpenAPI per version.', link: '/examples/versions' },
  { title: 'Rate limit', text: 'A default per IP, a quota per API key, #[RateLimit] and an endpoint without a limit.', link: '/examples/rate-limit' },
  { title: 'Testing', text: 'PHPUnit tests for a handler, params, outputs and authorization.', link: '/examples/testing' },
]" />

All examples need PHP 8.3 or newer and Composer. They use `composer.json` with `tomaj/nette-api: ^3.6`.

## FAQ

### Which example should I start with?

[Hello world](/examples/hello-world), then the one for the feature you need.

### Do the examples use a database?

No, data are in memory or in files, so `composer install` is the only setup.

### Can I copy an example as a starting point?

Yes, the config, the router and the bootstrap are a complete application. Read [Getting started](/guide/getting-started) for what each part does.

### How does CI test them?

`examples/ci/example.sh NAME` copies the project, points Composer at the checked out library and runs `e2e.sh`.

### Which ports do they use?

8081 to 8087 and 8090 to 8098, one per example (logging and errors uses two), so they can run side by side.
