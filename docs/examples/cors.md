---
title: 'Example: CORS'
description: 'Runnable Nette API example for a browser frontend on another origin - a fixed Access-Control-Allow-Origin and preflight OPTIONS answered by CorsPreflightHandler.'
---

# Example: CORS

A notes API for a frontend at `http://localhost:5173`: `GET` and a JSON `POST` to `/api/v1/notes`.

<ProjectSource name="cors" :files="['config/common.neon', 'app/Api/NoteCreateHandler.php', 'e2e.sh']" />

<Diagram name="examples-cors-1" alt="The browser at localhost:5173 sends OPTIONS, gets 200 with the CORS headers, then sends the JSON POST and gets 201" />

::: tip Several origins
This example uses one fixed origin, which works in 3.5.0 and after. For more origins, 3.6.0 adds `setCorsAllowedOrigins()`. See [CORS](/guide/cors#allowed-origins).
:::

## The services

<<< @/../examples/cors/config/common.neon{yaml}

## The handler

<<< @/../examples/cors/app/Api/NoteCreateHandler.php

## The test

<<< @/../examples/cors/e2e.sh{bash}

## FAQ

### Why does the frontend need a preflight?

A `POST` with `Content-Type: application/json` is not a simple request, so the browser asks with `OPTIONS` first.

### Why is Content-Type in Allow-Headers?

The default preflight headers allow only `Authorization` and `X-Requested-With`; a JSON body needs `Content-Type`.

### What does Max-Age 600 do?

The browser caches the preflight answer for 10 minutes.

### Where do I set the origin per environment?

`frontendOrigin` is a NEON parameter; override it in your local or production config.

### Does OPTIONS to an unknown URL work?

No, it answers `404`, as the test shows. See [CORS](/guide/cors#preflight-requests).
