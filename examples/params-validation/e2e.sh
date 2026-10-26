#!/usr/bin/env bash
# End-to-end check: valid and invalid query parameters and JSON bodies.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8082

request "$BASE/api/v1/users?status=active&limit=1"
expect_status 200 "filtered list"
expect_body '{"users":[{"id":1,"name":"Alice","email":"alice@example.com","status":"active"}]}' "filtered list"
ok "GET ?status=active&limit=1 returns one active user"

request "$BASE/api/v1/users?id%5B%5D=2&id%5B%5D=3"
expect_status 200 "multi id"
expect_body '"name":"Bob"' "multi id"
expect_body '"name":"Carol"' "multi id"
ok "GET ?id[]=2&id[]=3 returns both users"

request "$BASE/api/v1/users?status=deleted"
expect_status 400 "value outside setAvailableValues"
expect_body '"message":"wrong input"' "value outside setAvailableValues"
expect_body '"status":["Field contains not available value(s)"]' "value outside setAvailableValues"
ok "GET ?status=deleted answers 400 with the field error"

request "$BASE/api/v1/users?limit=abc"
expect_status 200 "non-numeric integer"
expect_body '"name":"Carol"' "non-numeric integer"
ok "GET ?limit=abc passes as null, the handler falls back to 10"

request "$BASE/api/v1/users?id%5B%5D=x"
expect_status 200 "non-numeric multi item"
ok "GET ?id[]=x passes too, the item becomes null"

request -X POST -H 'Content-Type: application/json' -d '{"name":"Dave","email":"dave@example.com"}' "$BASE/api/v1/users"
expect_status 201 "valid body"
expect_body '"user":{"id":4,"name":"Dave","email":"dave@example.com","status":"active"}' "valid body"
ok "POST a valid body answers 201"

request -X POST -H 'Content-Type: application/json' -d '{"name":"Dave"}' "$BASE/api/v1/users"
expect_status 400 "missing property"
expect_body 'The property email is required' "missing property"
ok "POST without email answers 400 with the schema error"

request -X POST -H 'Content-Type: application/json' -d '{"name":' "$BASE/api/v1/users"
expect_status 400 "broken JSON"
expect_body '"user":["Syntax error"]' "broken JSON"
ok "POST broken JSON answers 400"

request -X POST "$BASE/api/v1/users"
expect_status 400 "empty body"
expect_body '"user":["missing data"]' "empty body"
ok "POST without a body answers 400"
