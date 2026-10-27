# Rate limit

A `RateLimitInterface` that allows three requests per hour per API key, with a custom 429 body.

Documentation: [tomaj.github.io/nette-api/examples/rate-limit](https://tomaj.github.io/nette-api/examples/rate-limit)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -i -H "X-Api-Key: key-a" http://127.0.0.1:8000/api/v1/quote
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8095 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh rate-limit`.
