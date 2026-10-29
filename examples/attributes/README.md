# Attribute endpoints

Endpoints declared right on the handler classes with `#[Get]`, `#[Post]`, `#[Authorization]` and `#[RateLimit]`. The neon config only says where the handlers are (`api: discovery: paths:`). There is no endpoint list to keep in sync.

Documentation: [tomaj.github.io/nette-api/examples/attributes](https://tomaj.github.io/nette-api/examples/attributes)

Needs nette-api 4.0.

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -i http://127.0.0.1:8000/api/v1/hello
curl -i -H 'Authorization: Bearer secret-token' http://127.0.0.1:8000/api/v1/users
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8098 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh attributes`.
