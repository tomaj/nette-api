# Custom authorization

Your own `ApiAuthorizationInterface`: bearer tokens with roles, the user passed to the handler, and an any-of combination with an API key.

Documentation: [tomaj.github.io/nette-api/examples/custom-authorization](https://tomaj.github.io/nette-api/examples/custom-authorization)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -H "Authorization: Bearer alice-token" http://127.0.0.1:8000/api/v1/report
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8090 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh custom-authorization`.
