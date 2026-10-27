#!/usr/bin/env bash
# End-to-end check: three requests per hour per API key, then 429 with Retry-After.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
rm -f temp/rate-*
serve 8095

for remaining in 3 2 1; do
    request -H 'X-Api-Key: key-a' "$BASE/api/v1/quote"
    expect_status 200 "request with $remaining left"
    expect_header 'X-RateLimit-Limit: 3' "limit header"
    expect_header "X-RateLimit-Remaining: $remaining" "remaining header"
done
ok "three requests pass, X-RateLimit-Remaining counts down"

request -H 'X-Api-Key: key-a' "$BASE/api/v1/quote"
expect_status 429 "fourth"
expect_header 'X-RateLimit-Remaining: 0' "fourth"
expect_header 'Retry-After:' "fourth"
expect_body '"message":"Slow down"' "fourth"
ok "the fourth request answers 429 with Retry-After and the custom body"

request -H 'X-Api-Key: key-b' "$BASE/api/v1/quote"
expect_status 200 "other key"
expect_header 'X-RateLimit-Remaining: 3' "other key"
ok "another key has its own bucket"

request -H 'X-Api-Key: wrong' "$BASE/api/v1/quote"
expect_status 401 "wrong key"
expect_header 'X-RateLimit-Remaining: 3' "wrong key"
ok "a wrong key is counted too, the limit runs before the authorization"
