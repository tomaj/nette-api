---
title: 'Example: params and validation'
description: 'Runnable Nette API example with query params, allowed values, multi values, integer params and a JSON body checked by a JSON schema, with the 400 responses.'
---

# Example: params and validation

A user list with filters, `GET /api/v1/users`, and user creation with a JSON body, `POST /api/v1/users`.

<ProjectSource name="params-validation" :files="['app/Api/UserListHandler.php', 'app/Api/UserCreateHandler.php', 'app/Api/schema/user-create.json', 'config/common.neon', 'e2e.sh']" />

<Diagram name="examples-params-validation-1" alt="Query params and the JSON body are checked by InputValidator and JsonSchemaValidator; invalid input answers 400, valid input reaches the handler" />

## Query params

<<< @/../examples/params-validation/app/Api/UserListHandler.php

- `status` allows only `active` or `inactive`; `?status=deleted` answers `400`.
- `id` is a multi value, `?id[]=2&id[]=3`.
- `limit` is an integer. `?limit=abc` is **not** an error in 3.x: the value becomes `null` and the handler falls back to 10. See [Params](/guide/params#types).

## JSON body

<<< @/../examples/params-validation/app/Api/UserCreateHandler.php

<<< @/../examples/params-validation/app/Api/schema/user-create.json

A missing `email`, broken JSON or an empty body answers `400`.

## The test

<<< @/../examples/params-validation/e2e.sh{bash}

## FAQ

### What does a 400 response contain?

`{"status":"error","message":"wrong input"}`, and the errors per field in `detail` when details are on. See [Params](/guide/params#the-400-response).

### Why are the outputs declared too?

The list and the created user are checked against `user-list.json` and `user.json` in development. See [Outputs](/guide/outputs).

### How do I make a param required?

Call `setRequired()`. A missing value answers `400` with `Field is required`.

### Can I validate a POST form instead of JSON?

Use `PostInputParam` for each field. See [Params](/guide/params#param-classes).

### Do the schemas show up in OpenAPI?

Yes, as the request body and response schemas. See [OpenAPI](/guide/openapi).
