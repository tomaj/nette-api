# Logging and errors

A request logger that masks secrets, an own error handler for 503, Tracy logs and `APP_ENV=production`.

Documentation: [tomaj.github.io/nette-api/examples/logging-errors](https://tomaj.github.io/nette-api/examples/logging-errors)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl http://127.0.0.1:8000/api/v1/ping
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8086 and 8087 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh logging-errors`.
