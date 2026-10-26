#!/usr/bin/env bash
# End-to-end check: the CORS headers a browser needs for a cross-origin GET and a JSON POST.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8084

request -H 'Origin: http://localhost:5173' "$BASE/api/v1/notes"
expect_status 200 "GET"
expect_header 'Access-Control-Allow-Origin: http://localhost:5173' "GET"
expect_header 'Access-Control-Allow-Methods: POST, DELETE, PUT, GET, OPTIONS' "GET"
ok "GET carries Access-Control-Allow-Origin for the frontend"

request -X OPTIONS -H 'Origin: http://localhost:5173' -H 'Access-Control-Request-Method: POST' \
    -H 'Access-Control-Request-Headers: content-type' "$BASE/api/v1/notes"
expect_status 200 "preflight"
expect_header 'Access-Control-Allow-Headers: Content-Type' "preflight"
expect_header 'Access-Control-Allow-Headers: Authorization' "preflight"
expect_header 'Access-Control-Max-Age: 600' "preflight"
ok "OPTIONS is answered by the global preflight handler"

request -X POST -H 'Origin: http://localhost:5173' -H 'Content-Type: application/json' -d '{"text":"Call mom"}' "$BASE/api/v1/notes"
expect_status 201 "POST"
expect_header 'Access-Control-Allow-Origin: http://localhost:5173' "POST"
ok "the JSON POST after the preflight succeeds"

request -X OPTIONS "$BASE/api/v1/unknown"
expect_status 404 "preflight for an unknown endpoint"
ok "OPTIONS for an endpoint that does not exist answers 404"
