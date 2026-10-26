#!/usr/bin/env bash
# End-to-end check: two versions of one endpoint, a deprecated v1 and OpenAPI per version.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8094

request "$BASE/api/v1/users?id=7"
expect_status 200 "v1"
expect_body '{"id":"7","name":"Ada Lovelace"}' "v1"
expect_header 'Deprecation: true' "v1"
expect_header 'Link: </api/v2/users?id=7>; rel="successor-version"' "v1"
ok "v1 still answers the old shape and points to v2"

request "$BASE/api/v2/users?id=7"
expect_status 200 "v2"
expect_body '{"id":"7","first_name":"Ada","last_name":"Lovelace"}' "v2"
no_header 'Deprecation' "v2"
ok "v2 answers the new shape"

request "$BASE/api/v3/users?id=7"
expect_status 404 "v3"
ok "a version that is not registered answers 404"

request "$BASE/api/v1/openapi"
expect_body '"deprecated":true' "openapi v1"
expect_body '"/api/v1/users"' "openapi v1"
request "$BASE/api/v2/openapi"
expect_body '"/api/v2/users"' "openapi v2"
if [[ "$BODY" == *'"deprecated"'* || "$BODY" == *'/api/v1/'* ]]; then echo "fail: openapi v2 lists a deprecated or v1 endpoint" >&2; exit 1; fi
ok "each version has its own OpenAPI, v1 marks users deprecated"
