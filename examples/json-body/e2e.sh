#!/usr/bin/env bash
# End-to-end check: a JSON body validated by a schema, and a raw CSV body parsed by the handler.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8091
JSON=(-H 'Content-Type: application/json')

request -X POST "${JSON[@]}" -d '{"customer":"Ada","items":[{"sku":"book","quantity":2},{"sku":"pen","quantity":3}]}' "$BASE/api/v1/orders"
expect_status 201 "valid order"
expect_body '{"customer":"Ada","lines":2,"pieces":5}' "valid order"
ok "a valid JSON body reaches the handler as an array"

request -X POST "${JSON[@]}" "$BASE/api/v1/orders"
expect_status 400 "empty body"
expect_body '"detail":{"order":["missing data"]}' "empty body"
ok "an empty body answers 400 missing data"

request -X POST "${JSON[@]}" -d '{"customer":' "$BASE/api/v1/orders"
expect_status 400 "broken JSON"
expect_body '"order":["Syntax error"]' "broken JSON"
ok "broken JSON answers 400 with the json_decode error"

request -X POST "${JSON[@]}" -d '{"customer":"Ada","items":[{"sku":"book","quantity":0}]}' "$BASE/api/v1/orders"
expect_status 400 "schema"
expect_body 'quantity' "schema"
ok "a body that breaks the schema answers 400 with the failing field"

request -X POST "${JSON[@]}" -d '{"customer":"Ada","items":[{"sku":"book","quantity":1}],"coupon":"FREE"}' "$BASE/api/v1/orders"
expect_status 400 "additional property"
ok "an unknown property answers 400 because of additionalProperties: false"

request -X PUT -H 'Content-Type: text/csv' --data-binary $'sku,price\nbook,12.5\npen,1.2\n' "$BASE/api/v1/products/import"
expect_status 200 "csv"
expect_body '{"imported":2,"products":[{"sku":"book","price":12.5},{"sku":"pen","price":1.2}]}' "csv"
ok "a CSV body arrives as a string and the handler parses it"

request -X PUT -H 'Content-Type: text/csv' --data-binary $'name,cost\nbook,1\n' "$BASE/api/v1/products/import"
expect_status 422 "wrong csv header"
ok "the handler answers 422 for a CSV with the wrong header"

request -X PUT "$BASE/api/v1/products/import"
expect_status 400 "empty csv"
expect_body '"csv":["Field is required"]' "empty csv"
ok "an empty raw body answers 400"
