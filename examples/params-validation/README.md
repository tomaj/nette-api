# Params and validation

Query params with allowed and multi values, an integer param and a JSON body checked by a JSON schema.

Documentation: [tomaj.github.io/nette-api/examples/params-validation](https://tomaj.github.io/nette-api/examples/params-validation)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/api/v1/users?status=active"
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8082 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh params-validation`.
