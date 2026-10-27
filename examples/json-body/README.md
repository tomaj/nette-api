# JSON and raw body

A JSON body checked by a JSON schema with `JsonInputParam`, and a CSV body read with `RawInputParam`.

Documentation: [tomaj.github.io/nette-api/examples/json-body](https://tomaj.github.io/nette-api/examples/json-body)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -X POST -H 'Content-Type: application/json' -d '{"customer":"Ada","items":[{"sku":"book","quantity":2}]}' http://127.0.0.1:8000/api/v1/orders
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8091 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh json-body`.
