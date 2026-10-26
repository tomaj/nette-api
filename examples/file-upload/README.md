# File upload

One file and many files with `FileInputParam`, size and type checked in the handler.

Documentation: [tomaj.github.io/nette-api/examples/file-upload](https://tomaj.github.io/nette-api/examples/file-upload)

## Run it

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
curl -F avatar=@photo.png -F user=ada http://127.0.0.1:8000/api/v1/avatar
```

## Test it

```bash
./e2e.sh
```

`e2e.sh` starts the PHP built-in server on port 8092 and checks every response with curl. CI runs it against the current code of the library with `examples/ci/example.sh file-upload`.
