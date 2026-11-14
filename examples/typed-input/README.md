# Typed input

A small API whose handlers extend `TypedHandler` and read a readonly DTO: query, path, header, JSON or form body and a multipart file.

Documentation: [tomaj.github.io/nette-api/examples/typed-input](https://tomaj.github.io/nette-api/examples/typed-input)

Needs nette-api 4.0.

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/api/v1/users/list?page=2&status=active"
curl -H 'X-Request-Id: req-01' http://127.0.0.1:8000/api/v1/users/42/detail
curl -X POST -H 'Content-Type: application/json' -d '{"customerEmail":"ada@example.com","address":{"street":"Main 1","postalCode":"81101"},"items":[{"sku":"BOOK-1","quantity":2}]}' http://127.0.0.1:8000/api/v1/orders
curl http://127.0.0.1:8000/api/v1/openapi
```

`GET /api/v1/users/list` reads `page` and `status` from the query string. `GET /api/v1/users/42/detail` reads the path id and `X-Request-Id`. `POST /api/v1/orders` accepts the same nested order as JSON or as a form. `POST /api/v1/avatar` accepts a multipart file. `GET /api/v1/openapi` is the generated document.

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8099 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh typed-input`.
