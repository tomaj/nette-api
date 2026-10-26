#!/usr/bin/env bash
# End-to-end check: request logging, error responses and how APP_ENV=production changes them.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
rm -f log/api.log log/exception.log log/error.log

# development: APP_ENV is not set
APP_ENV= serve 8086
DEV="$BASE"

request -H 'Authorization: Bearer top-secret' -H 'Cookie: session=cookie-secret' -H 'X-Trace: visible-value' -A 'e2e/1.0' "$DEV/api/v1/ping"
expect_status 200 "ping"
ok "GET /api/v1/ping works"

grep -qF '"code":200' log/api.log || { echo "fail: log/api.log has no line for the ping" >&2; exit 1; }
grep -qF '"Authorization":"***"' log/api.log || { echo "fail: the Authorization header is not masked" >&2; cat log/api.log >&2; exit 1; }
if grep -qF 'top-secret' log/api.log; then echo "fail: the token leaked into log/api.log" >&2; exit 1; fi
grep -qF '"Cookie":"***"' log/api.log && ! grep -qF 'cookie-secret' log/api.log || { echo "fail: the Cookie header is not masked" >&2; exit 1; }
grep -qF '"X-Trace":"visible-value"' log/api.log || { echo "fail: a header that is not on the list was masked" >&2; exit 1; }
ok "the request is in log/api.log, Authorization and Cookie masked, other headers kept"

request "$DEV/api/v1/fail?kind=crash"
expect_status 500 "exception"
expect_body '"message":"Internal server error"' "exception"
expect_body '"detail":"Database connection refused"' "exception"
[[ -s log/exception.log ]] || { echo "fail: Tracy did not log the exception" >&2; exit 1; }
ok "an exception gives 500 with the detail, Tracy logs it"

request "$DEV/api/v1/fail?kind=maintenance"
expect_status 503 "custom error handler"
expect_body 'Down for maintenance' "custom error handler"
ok "AppErrorHandler turns MaintenanceException into 503"

request "$DEV/api/v1/fail"
expect_status 400 "wrong input"
expect_body '"message":"wrong input","detail":{"kind":["Field is required"]}' "wrong input"
if grep -qF '"code":400' log/api.log; then echo "fail: requests rejected before the handler should not be logged" >&2; exit 1; fi
ok "a missing parameter gives 400 with the detail and is not logged"

request "$DEV/api/v1/broken"
expect_status 500 "output schema"
expect_body '"detail":' "output schema"
ok "a response that breaks its output schema gives 500"

# production: no details and no output schema validation
APP_ENV=production serve 8087
PROD="$BASE"

request "$PROD/api/v1/fail?kind=crash"
expect_status 500 "production exception"
expect_body '{"status":"error","message":"Internal server error"}' "production exception"
ok "with APP_ENV=production the 500 has no detail"

request "$PROD/api/v1/fail"
expect_status 400 "production wrong input"
expect_body '{"status":"error","message":"wrong input"}' "production wrong input"
ok "with APP_ENV=production the 400 has no detail"

request "$PROD/api/v1/broken"
expect_status 200 "production output schema"
expect_body '"count":"three"' "production output schema"
ok "with APP_ENV=production the output schema is not checked"
