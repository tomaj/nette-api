# Testing

PHPUnit tests for a handler, its params, its output schemas and the authorization, without a web server.

Documentation: [tomaj.github.io/nette-api/examples/testing](https://tomaj.github.io/nette-api/examples/testing)

## Run it

```bash
composer install
vendor/bin/phpunit
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` runs the PHPUnit tests, then starts the PHP built-in server on port 8096 for one request. CI runs it against the current code of the library with `examples/ci/example.sh testing`.
