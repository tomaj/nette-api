#!/usr/bin/env bash
# End-to-end check: every framework error is application/problem+json, then content negotiation.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
rm -rf temp/cache log

# --- development: problem details for every error, exception messages in "detail"
serve 8091
P='https://api.example.com/problems/'

request -X POST -d 'product=book&quantity=2' "$BASE/api/v1/orders"
expect_status 201 "valid order"
expect_header 'Content-Type: application/json' "success stays application/json"
ok "POST /api/v1/orders with valid input answers 201 JSON"

request -X POST -d 'quantity=9' "$BASE/api/v1/orders"
expect_status 400 "invalid input"
expect_header 'Content-Type: application/problem+json' "400 content type"
expect_body "\"type\":\"${P}validation-error\",\"status\":400,\"title\":\"Bad Request\",\"detail\":\"Request parameters are not valid.\"" "400 members"
expect_body '"errors":[{"parameter":"product","detail":"Field is required"},{"parameter":"quantity"' "400 errors list"
ok "invalid input answers 400 problem with an errors list"

request -X POST -d 'product=sold-out&quantity=2' "$BASE/api/v1/orders"
expect_status 409 "business error"
expect_header 'Content-Type: application/problem+json' "409 content type"
expect_body "{\"type\":\"${P}out-of-stock\",\"status\":409,\"title\":\"Out of stock\",\"detail\":\"Product sold-out has 0 pieces left, 2 requested.\",\"instance\":\"/api/v1/orders\",\"product\":\"sold-out\",\"available\":0}" "409 body"
ok "a handler returns its own 409 problem"

request -X POST -d 'product=crash&quantity=1' "$BASE/api/v1/orders"
expect_status 500 "exception"
expect_header 'Content-Type: application/problem+json' "500 content type"
expect_body "{\"type\":\"${P}internal-error\",\"status\":500,\"title\":\"Internal Server Error\",\"detail\":\"SQLSTATE[08006] connection to orders-db:5432 refused\"}" "500 debug"
grep -q "orders-db:5432 refused" log/exception.log || { echo "fail: the exception is not in log/exception.log" >&2; exit 1; }
ok "an exception answers 500 problem with the message in development and is logged"

request "$BASE/api/v1/me"
expect_status 401 "no token"
expect_body "{\"type\":\"${P}unauthorized\",\"status\":401,\"title\":\"Unauthorized\",\"detail\":\"Authorization header HTTP_Authorization is not set\"}" "401 body"
ok "a missing token answers 401 problem"

request -H 'Authorization: Bearer secret-token' "$BASE/api/v1/me"
expect_status 200 "token"
ok "a valid token answers 200"

request "$BASE/api/v1/limited"
expect_status 429 "rate limit"
expect_header 'Content-Type: application/problem+json' "429 content type"
expect_header 'Retry-After: 30' "429 Retry-After"
expect_body "{\"type\":\"${P}rate-limited\",\"status\":429,\"title\":\"Too Many Requests\",\"detail\":\"Too many requests. Retry after 30 seconds.\",\"limit\":10,\"retryAfter\":30}" "429 body"
ok "an exhausted rate limit answers 429 problem"

request "$BASE/api/v1/nothing"
expect_status 404 "unknown endpoint"
expect_header 'Content-Type: application/problem+json' "404 content type"
expect_body "{\"type\":\"${P}not-found\",\"status\":404,\"title\":\"Not Found\",\"detail\":\"Unknown api endpoint\"}" "404 body"
ok "an unknown endpoint answers 404 problem"

request "$BASE/api/v1/openapi"
expect_status 200 "openapi"
expect_body '"ProblemDetails":{"type":"object","description":"RFC 9457 problem details"' "ProblemDetails schema"
expect_body '"application/problem+json; charset=utf-8":{"schema":{"$ref":"#/components/schemas/ProblemDetails"}}' "error responses reference it"
ok "the OpenAPI spec documents errors with the ProblemDetails schema"

# --- production: no exception message
APP_ENV=production serve 8092
request -X POST -d 'product=crash&quantity=1' "$BASE/api/v1/orders"
expect_status 500 "production exception"
expect_body "{\"type\":\"${P}internal-error\",\"status\":500,\"title\":\"Internal Server Error\"}" "500 production"
if [[ "$BODY" == *SQLSTATE* ]]; then echo "fail: production leaks the exception message" >&2; exit 1; fi
ok "production hides the exception message"

request -X POST -d 'quantity=9' "$BASE/api/v1/orders"
expect_body '"errors":[{"parameter":"product","detail":"Field is required"}' "400 errors in production"
ok "validation errors are sent in production too"

# --- negotiation: problem details only when the client asks for them
rm -rf temp/cache
ERRORS=negotiate serve 8093
request "$BASE/api/v1/nothing"
expect_status 404 "negotiate default"
expect_header 'Content-Type: application/json' "legacy content type"
expect_body '{"status":"error","message":"Unknown api endpoint"}' "legacy body"
expect_header 'Vary: Accept' "legacy Vary"
ok "without Accept the 3.x error format is sent, with Vary: Accept"

request -H 'Accept: application/problem+json, application/json;q=0.5' "$BASE/api/v1/nothing"
expect_status 404 "negotiate problem"
expect_header 'Content-Type: application/problem+json' "problem content type"
expect_body "\"type\":\"${P}not-found\"" "problem body"
expect_header 'Vary: Accept' "problem Vary"
ok "Accept: application/problem+json gets a problem, with Vary: Accept"

request -H 'Accept: application/*' "$BASE/api/v1/nothing"
expect_header 'Content-Type: application/json' "wildcard content type"
ok "Accept: application/* gets the 3.x error format"
