# Problem Details

RFC 9457 error responses (`application/problem+json`) for invalid input, a missing token, an exhausted rate limit, an unknown endpoint, an exception and a business error from a handler. Also shows the `ProblemDetails` schema in OpenAPI and content negotiation.

Documentation: [tomaj.github.io/nette-api/examples/problem-details](https://tomaj.github.io/nette-api/examples/problem-details)

Needs nette-api 4.0.

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -i -X POST -d 'quantity=9' http://127.0.0.1:8000/api/v1/orders
```

`ERRORS=negotiate php -S ...` sends problem details only to clients with `Accept: application/problem+json`.

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on ports 8091, 8092 and 8093 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh problem-details`.
