#!/usr/bin/env bash
# End-to-end check: the OpenAPI document, the endpoint listing and the web console.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8085

request "$BASE/api/v1/openapi"
expect_status 200 "OpenAPI JSON"
expect_body '"openapi":"3.0.0"' "OpenAPI JSON"
expect_body '"title":"Bookshelf API"' "OpenAPI JSON"
expect_body '"summary":"List books"' "OpenAPI JSON"
expect_body '"Bearer":{"type":"http","scheme":"bearer"}' "OpenAPI JSON"
ok "GET /api/v1/openapi returns an OpenAPI 3.0 document"

request "$BASE/api/v1/openapi?format=yaml"
expect_status 200 "OpenAPI YAML"
expect_body 'openapi: 3.0.0' "OpenAPI YAML"
ok "GET /api/v1/openapi?format=yaml returns YAML"

request "$BASE/api/v1/endpoints"
expect_status 200 "listing"
expect_body '"method":"POST"' "listing"
ok "GET /api/v1/endpoints lists the endpoints"

request "$BASE/console"
expect_status 200 "console listing"
expect_body '/api/v1/openapi?format=yaml' "{apiLink} in the template"
expect_body 'v1/books/' "console listing"
ok "GET /console lists the endpoints"

jar="$(mktemp)"
# the cookie jar keeps the _nss cookie of Nette forms, which accept a POST only from the same site
request -c "$jar" -b "$jar" "$BASE/console/show/GET/1/books"
expect_status 200 "console form"
expect_body 'name="since"' "console form"
ok "GET /console/show/GET/1/books renders the form for the since parameter"

request -c "$jar" -b "$jar" -d "api_url=$BASE/api/v1/books/&api_method=GET&since=1980&timeout=10&_do=apiConsole-consoleForm-submit&send=1" "$BASE/console/show/GET/1/books"
rm -f "$jar"
expect_status 200 "console request"
expect_body 'Neuromancer' "console request"
ok "submitting the console form calls the endpoint and shows the response"
