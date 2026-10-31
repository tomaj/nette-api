#!/usr/bin/env bash
# End-to-end check: an app-wide default limit, an endpoint with two policies, an attribute limit and an endpoint without a limit.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
rm -rf temp/cache
serve 8095

for remaining in 2 1 0; do
    request -H 'X-Api-Key: key-a' "$BASE/api/v1/quote"
    expect_status 200 "request with $remaining left"
    expect_header 'RateLimit-Policy: "perIp";q=100;w=60' "perIp policy"
    expect_header 'RateLimit-Policy: "perKey";q=3;w=3600' "perKey policy"
    expect_header "RateLimit: \"perKey\";r=$remaining;" "perKey remaining"
done
ok "GET /quote counts perIp and perKey, RateLimit counts down"

request -H 'X-Api-Key: key-a' "$BASE/api/v1/quote"
expect_status 429 "fourth"
expect_header 'RateLimit: "perKey";r=0;' "fourth"
expect_header 'Retry-After:' "fourth"
expect_body '"message":"Too many requests. Retry after' "fourth"
ok "the fourth request of a key answers 429 with Retry-After"

request -H 'X-Api-Key: key-b' "$BASE/api/v1/quote"
expect_status 200 "other key"
expect_header 'RateLimit: "perKey";r=2;' "other key"
ok "another key has its own quota"

request -H 'X-Api-Key: wrong' "$BASE/api/v1/quote"
expect_status 401 "wrong key"
expect_header 'RateLimit: "perIp";r=94;' "wrong key"
no_header 'RateLimit-Policy: "perKey"' "wrong key"
ok "a wrong key is counted by perIp before the authorization, perKey needs an identity"

for i in 1 2; do
    request "$BASE/api/v1/ping"
    expect_status 200 "ping $i"
    expect_header 'RateLimit-Policy: "ping";q=2;w=60' "ping"
done
request "$BASE/api/v1/ping"
expect_status 429 "third ping"
expect_header 'Retry-After: 30' "third ping"
ok "#[RateLimit(limit: 2, ...)] replaces the default, the third ping waits 30 seconds"

for i in 1 2 3 4 5; do
    request "$BASE/api/v1/status"
    expect_status 200 "status $i"
    no_header 'RateLimit' "status"
done
ok "rateLimit: false turns the default off"
