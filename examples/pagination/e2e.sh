#!/usr/bin/env bash
# End-to-end check: typed query params, allowed values, prev/next links and an output schema.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8093

request "$BASE/api/v1/products"
expect_status 200 "defaults"
expect_body '"page":1,"per_page":10,"total":23' "defaults"
expect_body '"links":{"self":"/api/v1/products?page=1&per_page=10","next":"/api/v1/products?page=2&per_page=10"}' "defaults"
ok "without params the defaults apply and only a next link is given"

request "$BASE/api/v1/products?page=3&per_page=10"
expect_body '"items":[{"id":21,"name":"Product 21"},{"id":22,"name":"Product 22"},{"id":23,"name":"Product 23"}]' "last page"
expect_body '"prev":"/api/v1/products?page=2&per_page=10"}' "last page"
ok "the last page has three items and only a prev link"

request "$BASE/api/v1/products?page=2&per_page=5"
expect_body '{"id":6,"name":"Product 6"}' "per_page 5"
ok "the string 5 from the URL arrives as the integer 5"

request "$BASE/api/v1/products?per_page=7"
expect_status 400 "per_page not allowed"
expect_body '"per_page":["Field contains not available value(s)"]' "per_page not allowed"
ok "a per_page outside 5, 10, 20 answers 400"

request "$BASE/api/v1/products?page=0"
expect_status 400 "page 0"
expect_body 'page must be a whole number from 1' "page 0"
request "$BASE/api/v1/products?page=abc"
expect_status 400 "page abc"
ok "page 0 or abc answers 400 from the handler"

request "$BASE/api/v1/products?page=9"
expect_status 404 "past the end"
expect_body 'last page is 3' "past the end"
ok "a page past the end answers 404"
