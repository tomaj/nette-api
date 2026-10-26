#!/usr/bin/env bash
# End-to-end check: roles from a bearer token, and one endpoint that accepts a bearer token or an API key.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8090

request "$BASE/api/v1/me"
expect_status 401 "no token"
expect_body '{"status":"error","message":"Missing bearer token"}' "no token"
ok "no token answers 401 with the message of the authorization"

request -H 'Authorization: Bearer wrong' "$BASE/api/v1/me"
expect_status 401 "unknown token"
expect_body '"Unknown token"' "unknown token"
ok "an unknown token answers 401"

request -H 'Authorization: Bearer bob-token' "$BASE/api/v1/me"
expect_status 200 "bob"
expect_body '{"name":"Bob","roles":["staff"]}' "bob"
ok "the handler reads the user that the authorization found"

request -H 'Authorization: Bearer bob-token' "$BASE/api/v1/report"
expect_status 401 "bob is not admin"
expect_body '"Role admin is required"' "bob is not admin"
ok "a user without the admin role cannot read the report"

request -H 'Authorization: Bearer alice-token' "$BASE/api/v1/report"
expect_status 200 "alice is admin"
expect_body '"orders":42' "alice is admin"
ok "an admin reads the report"

request -H 'X-API-Key: partner-key' "$BASE/api/v1/me/any"
expect_status 200 "api key"
expect_body '"name":"api-key client"' "api key"
ok "the any-of endpoint accepts an API key"

request -H 'Authorization: Bearer alice-token' "$BASE/api/v1/me/any"
expect_body '"name":"Alice"' "bearer on any-of"
ok "the any-of endpoint accepts a bearer token"

request "$BASE/api/v1/me/any"
expect_status 401 "neither"
expect_body 'Missing bearer token; ' "neither"
ok "with neither, the 401 says why both failed"
