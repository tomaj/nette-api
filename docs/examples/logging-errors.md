---
title: 'Example: logging and errors'
description: 'Runnable Nette API example: JSON lines request log with masked secret headers, an own error handler for 503, Tracy logs and error responses in production.'
---

# Example: logging and errors

Three endpoints that show what is logged and what the client sees, in development and in production.

<ProjectSource name="logging-errors" :files="['config/common.neon', 'app/FileApiLogger.php', 'app/AppErrorHandler.php', 'app/Api/FailHandler.php', 'e2e.sh']" />

<Diagram name="examples-logging-errors-1" alt="ping answers 200, fail answers 500, 503 or 400, broken answers 500 in development and 200 in production; handled requests go to api.log, exceptions to Tracy" />

::: tip Since 3.6.0
The presenter masks `Authorization`, `Cookie`, `X-Api-Key` and the other secret headers through `setMaskedLogHeaders()`, so the logger never sees them. With 3.5.0 remove that `setup` line and mask in the logger. `400`, `401` and `429` are not logged in 3.x. See [Logging](/guide/logging#masking-headers).
:::

| Request | Development | Production |
| --- | --- | --- |
| `/api/v1/ping` | `200`, logged | `200`, logged |
| `/api/v1/fail?kind=crash` | `500` with `detail`, in Tracy log | `500` without `detail` |
| `/api/v1/fail?kind=maintenance` | `503` from `AppErrorHandler` | `503` |
| `/api/v1/fail` | `400` with `detail`, not logged | `400` without `detail` |
| `/api/v1/broken` | `500`, output does not match | `200`, not checked |

## The services

<<< @/../examples/logging-errors/config/common.neon{yaml}

## The logger

<<< @/../examples/logging-errors/app/FileApiLogger.php

## The error handler

<<< @/../examples/logging-errors/app/AppErrorHandler.php

## The failing handler

<<< @/../examples/logging-errors/app/Api/FailHandler.php

## The test

<<< @/../examples/logging-errors/e2e.sh{bash}

## FAQ

### How is production switched on?

`EnvConfigurator('APP_ENV', 'production')` turns details and output checks off when `APP_ENV=production`. See [Outputs](/guide/outputs#when-outputs-are-validated).

### Where are the headers masked?

In the presenter, by `setMaskedLogHeaders()` in `common.neon`. The test sends a token and a cookie and checks that `api.log` has `***` and keeps `X-Trace`. See [Logging](/guide/logging#masking-headers).

### Why is the 400 not in api.log?

Wrong input returns before the logger in 3.x. See [Logging](/guide/logging#what-is-logged).

### Where are the exceptions?

In `log/exception.log` and Tracy's HTML files, written by `DefaultErrorHandler`.

### Why wrap DefaultErrorHandler instead of extending it?

It is `final`. See [Error handling](/guide/errors#your-own-error-handler).
