# Hello world

One GET endpoint with an optional `name` param, the smallest working setup.

Documentation: [tomaj.github.io/nette-api/examples/hello-world](https://tomaj.github.io/nette-api/examples/hello-world)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/api/v1/hello?name=Nette"
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8081 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh hello-world`.
