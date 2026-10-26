---
title: 'Example: JSON and raw body'
description: 'Runnable Nette API example that reads a JSON request body checked by a JSON schema with JsonInputParam, and a CSV body with RawInputParam.'
---

# Example: JSON and raw body

`POST /api/v1/orders` takes a JSON order checked by a schema. `PUT /api/v1/products/import` takes CSV as the raw body.

<ProjectSource name="json-body" :files="['config/common.neon', 'app/Api/schema/order.json', 'app/Api/OrderCreateHandler.php', 'app/Api/CsvImportHandler.php', 'e2e.sh']" />

<Diagram name="examples-json-body-1" alt="JsonInputParam decodes the body and validates it against order.json; a syntax or schema error answers 400; RawInputParam passes the CSV text to the handler" />

| Body | Response |
| --- | --- |
| a valid order | `201 {"customer":"Ada","lines":2,"pieces":5}` |
| none | `400`, `missing data` |
| `{"customer":` | `400 Syntax error` |
| `quantity: 0` or an unknown field | `400` with the schema errors |
| CSV with the header `sku,price` | `200 {"imported":2,"products":[...]}` |
| an empty PUT | `400 Field is required` |
| CSV with another header | `422` from the handler |

## The schema

<<< @/../examples/json-body/app/Api/schema/order.json{json}

## The JSON handler

`$params['order']` is the decoded array; the handler runs only when the schema passed.

<<< @/../examples/json-body/app/Api/OrderCreateHandler.php

## The raw body handler

<<< @/../examples/json-body/app/Api/CsvImportHandler.php

## The services

<<< @/../examples/json-body/config/common.neon{yaml}

## The test

<<< @/../examples/json-body/e2e.sh{bash}

## FAQ

### Does the client need Content-Type: application/json?

No, `JsonInputParam` reads `php://input` whatever the header says. Send it anyway, the console and OpenAPI do.

### Can one endpoint have a JSON body and query params?

Yes, add `GetInputParam` next to the `JsonInputParam`. Only one body param per endpoint makes sense, the body is read once.

### How do I reject unknown fields?

`"additionalProperties": false` in the schema, as in `order.json`. See [Input params](/guide/params).

### Where do I check things a schema cannot?

In the handler, like the CSV header check that answers `422`. Every code you return needs an output, see [Outputs](/guide/outputs).

### Is the schema shown in OpenAPI?

Yes, the JSON schema of a `JsonInputParam` becomes the request body of the endpoint. See [OpenAPI](/guide/openapi).
