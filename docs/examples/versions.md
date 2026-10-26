---
title: 'Example: API versions'
description: 'Runnable Nette API example with one endpoint in v1 and v2, v1 deprecated in OpenAPI with Deprecation and Link headers, and an OpenAPI document per version.'
---

# Example: API versions

`GET /api/v1/users` returns `name`, `GET /api/v2/users` returns `first_name` and `last_name`. v1 still works and tells clients to move.

<ProjectSource name="versions" :files="['config/common.neon', 'app/Api/UserV1Handler.php', 'app/Api/UserV2Handler.php', 'e2e.sh']" />

<Diagram name="examples-versions-1" alt="ApiDecider picks the v1 or v2 handler by the version in the URL; v1 adds Deprecation and Link headers; each version has its own OpenAPI document; v3 answers 404" />

| Request | Response |
| --- | --- |
| `/api/v1/users?id=7` | `200 {"id":"7","name":"Ada Lovelace"}` with `Deprecation: true` and a `Link` to v2 |
| `/api/v2/users?id=7` | `200 {"id":"7","first_name":"Ada","last_name":"Lovelace"}` |
| `/api/v3/users?id=7` | `404 Unknown api endpoint` |
| `/api/v1/openapi.json` | only the v1 endpoints, v1 marked deprecated |

## Version 1

`deprecated()` returns `true`, which OpenAPI shows. The headers come from `Nette\Http\IResponse`.

<<< @/../examples/versions/app/Api/UserV1Handler.php

## Version 2

<<< @/../examples/versions/app/Api/UserV2Handler.php

## The services

The version is the second argument of `EndpointIdentifier`.

<<< @/../examples/versions/config/common.neon{yaml}

## The test

<<< @/../examples/versions/e2e.sh{bash}

## FAQ

### Where is the version in the URL?

The route `api/v<version>/<package>[/<apiAction>]` puts it in the path; `ApiDecider` matches it with the method and package. See [ApiDecider and endpoints](/guide/api-decider).

### Can v2 reuse v1 code?

Yes, both handlers can use the same service; only the shape of the response differs here.

### What do the Deprecation and Link headers do?

They tell a client, or a gateway, that v1 is deprecated and where the successor is, without breaking it.

### Why one OpenAPI document per version?

`OpenApiHandler` lists the endpoints of its own version. Clients of v2 do not see v1. See [OpenAPI](/guide/openapi).

### Does a string version work?

Yes, `EndpointIdentifier` takes a string such as `'1.1'`, and the default route pattern accepts the dot in `/api/v1.1/...`.
