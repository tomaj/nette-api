# Pagination

Typed query params with defaults and allowed values, prev and next links, output schemas for every status code.

Documentation: [tomaj.github.io/nette-api/examples/pagination](https://tomaj.github.io/nette-api/examples/pagination)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/api/v1/products?page=2&per_page=5"
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8093 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh pagination`.
