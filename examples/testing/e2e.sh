#!/usr/bin/env bash
# Runs the PHPUnit tests, then one real request to show the tested pieces work together.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
vendor/bin/phpunit
echo "ok: the PHPUnit tests pass"

source ../ci/lib.sh
serve 8096
request -H 'Authorization: Bearer test-token' "$BASE/api/v1/articles?id=1"
expect_status 200 "article"
expect_body '{"id":1,"title":"Hello nette-api"}' "article"
request "$BASE/api/v1/articles?id=1"
expect_status 401 "no token"
ok "the same handler answers over HTTP"
