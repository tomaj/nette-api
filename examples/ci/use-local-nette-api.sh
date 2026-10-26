#!/usr/bin/env bash
# Points a Composer project at this checkout of nette-api instead of Packagist:
#   examples/ci/use-local-nette-api.sh /path/to/project [package...]
# Adds a path repository for the repository root, requires tomaj/nette-api from it and installs
# the project. Used by the examples workflow, so a pull request runs the examples against its own
# code. It rewrites the project's composer.json: run it on a copy.
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
project="${1:?usage: $0 /path/to/project [package...]}"
shift
cd "$project"
# shellcheck disable=SC2016 # $argv is PHP, not shell
repository="$(php -r 'echo json_encode(["type" => "path", "url" => $argv[1], "options" => ["symlink" => false, "versions" => ["tomaj/nette-api" => "3.99.0"]]], JSON_UNESCAPED_SLASHES);' "$root")"
composer config repositories.nette-api "$repository"
composer require --no-interaction --no-progress --with-all-dependencies "tomaj/nette-api:3.99.0" "$@"
# fail when Composer took nette-api from anywhere else
diff -rq "$root/src" vendor/tomaj/nette-api/src > /dev/null \
    || { echo "fail: vendor/tomaj/nette-api is not this checkout" >&2; exit 1; }
echo "tomaj/nette-api installed from $root"
