# Typed input

A small API whose handlers extend `TypedHandler` and read a readonly DTO.

```bash
composer install
./e2e.sh
```

`GET /api/v1/users/list` reads `page` and `status` from the query string. `GET /api/v1/users/42/detail` reads the path id and `X-Request-Id`. `POST /api/v1/orders` accepts the same nested order as JSON or as a form. `POST /api/v1/avatar` accepts a multipart file. `GET /api/v1/openapi` is the generated document.
