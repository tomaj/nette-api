# Versions

The same endpoint in v1 and v2, v1 marked deprecated in OpenAPI and with Deprecation and Link headers.

Documentation: [tomaj.github.io/nette-api/examples/versions](https://tomaj.github.io/nette-api/examples/versions)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -i "http://127.0.0.1:8000/api/v1/users?id=7"
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8094 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh versions`.
