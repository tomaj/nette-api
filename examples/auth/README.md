# Authorization and rate limit

Bearer token, X-API-Key header, HTTP Basic with `hash_equals()`, IP restrictions that cannot be spoofed, and 3 requests per minute.

Documentation: [tomaj.github.io/nette-api/examples/auth](https://tomaj.github.io/nette-api/examples/auth)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -H 'Authorization: Bearer secret-token' http://127.0.0.1:8000/api/v1/bearer
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8083 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh auth`.
