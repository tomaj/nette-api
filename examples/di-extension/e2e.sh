#!/usr/bin/env bash
# End-to-end check: the whole API is configured in the "api:" neon section of the DI extension.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
rm -rf temp/cache log
serve 8097
P='https://api.example.com/problems/'

request "$BASE/api/v1/hello"
expect_status 200 "public endpoint"
expect_body '{"message":"Hello from neon"}' "statement argument"
ok "GET /api/v1/hello is public and the handler got its neon argument"

request "$BASE/api/v1/users"
expect_status 401 "default authorization"
expect_header 'Content-Type: application/problem+json' "errors.format"
expect_body "\"type\":\"${P}unauthorized\"" "errors.typeBaseUri"
ok "defaults.authorization protects GET /api/v1/users, the error is a problem detail"

request -H 'Authorization: Bearer secret-token' "$BASE/api/v1/users"
expect_status 200 "token"
expect_body '{"users":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]}' "autowired repository"
ok "with the token the handler answers with users from an autowired service"

request -H 'Authorization: Bearer secret-token' "$BASE/api/v1/users/detail?id=2"
expect_status 200 "action"
expect_body '{"id":2,"name":"Bob"}' "detail"
ok "GET /api/v1/users/detail is a separate endpoint (action: detail)"

request -H 'Authorization: Bearer secret-token' "$BASE/api/v1/users/detail"
expect_status 400 "params"
expect_body "\"type\":\"${P}validation-error\"" "validation problem"
ok "a missing param answers 400 problem"

request -H 'Origin: https://app.example.com' "$BASE/api/v1/hello"
expect_header 'Access-Control-Allow-Origin: https://app.example.com' "allowed origin"
request -H 'Origin: https://evil.example.com' "$BASE/api/v1/hello"
no_header 'Access-Control-Allow-Origin' "other origin"
ok "cors.allowedOrigins answers only the listed origin"

request -X OPTIONS -H 'Origin: https://app.example.com' "$BASE/api/v1/users"
expect_status 200 "preflight"
expect_header 'Access-Control-Allow-Methods' "preflight methods"
ok "cors.preflight answers OPTIONS for every endpoint without a token"

for i in 1 2; do
    request -X POST -H 'Authorization: Bearer secret-token' -d 'name=Carol' "$BASE/api/v1/users"
    expect_status 201 "create $i"
done
expect_body '{"id":3,"name":"Carol"}' "created"
request -X POST -H 'Authorization: Bearer secret-token' -d 'name=Carol' "$BASE/api/v1/users"
expect_status 429 "rate limit"
expect_body "\"type\":\"${P}rate-limited\"" "429 problem"
ok "rateLimit: strict lets 2 POST requests through, the third one gets 429"

request "$BASE/api/v1/nothing"
expect_status 404 "unknown"
expect_body "\"type\":\"${P}not-found\"" "404 problem"
ok "an unknown endpoint answers 404 problem"

# the compiled container only calls addApi(), the handlers are created when they are used
grep -q "addApi" temp/cache/nette.configurator/*.php || { echo "fail: no addApi() in the compiled container" >&2; exit 1; }
ok "the compiled container registers the endpoints with addApi()"
