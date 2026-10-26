# OpenAPI and console

OpenAPI 3.0 in JSON and YAML, a JSON endpoint listing and the web console with the `{apiLink}` Latte tag.

Documentation: [tomaj.github.io/nette-api/examples/openapi-console](https://tomaj.github.io/nette-api/examples/openapi-console)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/api/v1/openapi?format=yaml"
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8085 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh openapi-console`.
