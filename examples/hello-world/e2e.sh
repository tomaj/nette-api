#!/usr/bin/env bash
# End-to-end check: start the app with the PHP built-in server and call the endpoint with curl.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8081

request "$BASE/api/v1/hello"
expect_status 200 "GET /api/v1/hello"
expect_body '{"message":"Hello world!"}' "default name"
ok "GET /api/v1/hello says hello to the world"

request "$BASE/api/v1/hello?name=Nette"
expect_body '{"message":"Hello Nette!"}' "name from the query"
ok "GET /api/v1/hello?name=Nette uses the parameter"

request "$BASE/api/v1/nothing"
expect_status 404 "unknown endpoint"
expect_body '"Unknown api endpoint"' "unknown endpoint"
ok "an unknown endpoint answers 404"

request -X POST "$BASE/api/v1/hello"
expect_status 404 "wrong method"
ok "POST to a GET endpoint answers 404"
