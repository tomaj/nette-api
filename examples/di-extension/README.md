# DI extension

The whole API in one `api:` section of the neon config: endpoints, a default bearer token authorization, a public endpoint, a rate limit, CORS with preflight, masked log headers and Problem Details errors. No `ApiDecider` setup, no `addApi()` lines.

Documentation: [tomaj.github.io/nette-api/examples/di-extension](https://tomaj.github.io/nette-api/examples/di-extension)

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

`e2e.sh` starts the PHP built-in server on port 8097 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh di-extension`.
