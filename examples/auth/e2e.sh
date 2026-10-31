#!/usr/bin/env bash
# End-to-end check: each authorization passes with the right credentials and answers 401 without them.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
rm -rf temp/cache
serve 8083

request -H 'Authorization: Bearer secret-token' "$BASE/api/v1/bearer"
expect_status 200 "bearer token"
expect_body '"endpoint":"v1/bearer/"' "bearer token"
ok "a valid bearer token passes"

request "$BASE/api/v1/bearer"
expect_status 401 "no bearer token"
expect_body '"message":"Authorization header HTTP_Authorization is not set"' "no bearer token"
ok "no Authorization header answers 401"

request -H 'Authorization: Bearer wrong' "$BASE/api/v1/bearer"
expect_status 401 "unknown bearer token"
expect_body "\"message\":\"Token doesn't exists or isn't active\"" "unknown bearer token"
ok "an unknown token answers 401"

request -H 'Authorization: Bearer office-token' -H 'X-Forwarded-For: 10.1.2.3' "$BASE/api/v1/bearer"
expect_status 401 "token from a wrong IP"
expect_body '"message":"Invalid IP"' "token from a wrong IP"
ok "an IP restricted token from 127.0.0.1 answers 401, a forged X-Forwarded-For does not help"

request -H 'X-API-Key: secret-token' "$BASE/api/v1/api-key"
expect_status 200 "api key"
ok "a valid X-API-Key passes"

request "$BASE/api/v1/api-key"
expect_status 401 "no api key"
expect_body '"message":"API key is not set"' "no api key"
ok "no X-API-Key answers 401"

request -u admin:change-me "$BASE/api/v1/basic"
expect_status 200 "basic auth"
ok "HTTP Basic with the right password passes"

request -u admin:wrong "$BASE/api/v1/basic"
expect_status 401 "basic auth wrong password"
expect_body '"message":"Incorrect username or password"' "basic auth wrong password"
ok "HTTP Basic with a wrong password answers 401"

for i in 1 2 3; do
    request "$BASE/api/v1/limited"
    expect_status 200 "rate limit request $i"
done
expect_header 'RateLimit-Policy: "perIp";q=3;w=60' "rate limit headers"
expect_header 'RateLimit: "perIp";r=0;' "rate limit headers"
request "$BASE/api/v1/limited"
expect_status 429 "rate limit exceeded"
expect_header 'Retry-After:' "rate limit exceeded"
expect_body '"message":"Too many requests. Retry after' "rate limit exceeded"
ok "the fourth request in a minute answers 429 with Retry-After"
