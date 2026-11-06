#!/usr/bin/env bash
# End-to-end check: typed DTOs for query, path, header, JSON, form and multipart.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8099
JSON=(-H 'Content-Type: application/json')

request "$BASE/api/v1/users/list"
expect_status 200 "defaults"
expect_body '{"page":1,"status":null}' "defaults"
ok "an omitted page uses its default and an omitted enum is null"

request "$BASE/api/v1/users/list?page=2&status=active"
expect_status 200 "query"
expect_body '{"page":2,"status":"active"}' "query"
ok "page is an integer and status is the backed enum"

request "$BASE/api/v1/users/list?page=1a"
expect_status 400 "strict int"
expect_body '"page":["Value must be an integer"]' "strict int"
ok "1a is rejected instead of being cast to 1"

request -H 'X-Request-Id: req-01' "$BASE/api/v1/users/42/detail"
expect_status 200 "path and header"
expect_body '{"id":42,"requestId":"req-01"}' "path and header"
ok "the path id and the request header reach the DTO"

request -X POST "${JSON[@]}" -d '{"customerEmail":"ada@example.com","address":{"street":"Main 1","postalCode":"81101"},"items":[{"sku":"BOOK-1","quantity":2}]}' "$BASE/api/v1/orders"
expect_status 201 "json"
expect_body '{"email":"ada@example.com","street":"Main 1","items":1,"quantity":2}' "json"
ok "a nested JSON body becomes DTOs"

request -X POST -H 'Content-Type: application/x-www-form-urlencoded' \
    --data 'customerEmail=ada%40example.com&address[street]=Main+1&address[postalCode]=81101&items[0][sku]=BOOK-1&items[0][quantity]=2' \
    "$BASE/api/v1/orders"
expect_status 201 "form"
expect_body '"quantity":2' "form"
ok "the same DTO accepts a form body in bracket notation"

request -X POST "${JSON[@]}" -d '{"customerEmail":"nope","address":{"street":"","postalCode":"abc"},"items":[{"sku":"A","quantity":1},{"sku":"B","quantity":0}]}' "$BASE/api/v1/orders"
expect_status 400 "nested errors"
expect_body '"items[1].quantity":["Range must be greater than or equal to 1"]' "nested quantity"
expect_body '"address.postalCode"' "nested postal code"
ok "nested failures use field paths"

request -X POST -H 'Content-Type: text/csv' --data 'a,b' "$BASE/api/v1/orders"
expect_status 415 "media type"
expect_body 'text/csv' "media type"
ok "a content type outside #[Consumes] defaults is 415"

png="$(mktemp)"
printf 'png' > "$png"
request -F "displayName=Ada" -F "avatar=@$png;filename=a.png;type=image/png" "$BASE/api/v1/avatar"
expect_status 201 "upload"
expect_body '{"name":"Ada","file":"a.png","size":3}' "upload"
ok "a multipart file arrives as FileUpload"
rm -f "$png"

request "$BASE/api/v1/openapi"
expect_status 200 "openapi"
expect_body '"in":"path"' "path parameter"
expect_body 'application/x-www-form-urlencoded' "form content type"
ok "OpenAPI lists the path parameter and both body content types"
