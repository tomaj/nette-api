# CORS

A fixed `Access-Control-Allow-Origin` and preflight `OPTIONS` for a frontend at `http://localhost:5173`.

Documentation: [tomaj.github.io/nette-api/examples/cors](https://tomaj.github.io/nette-api/examples/cors)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -i -X OPTIONS http://127.0.0.1:8000/api/v1/notes
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8084 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh cors`.
