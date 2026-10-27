#!/usr/bin/env bash
# Runs one example's e2e.sh against this checkout of nette-api: examples/ci/example.sh hello-world
# The example is copied to a temporary directory first, so the checkout stays untouched.
set -euo pipefail
examples="$(cd "$(dirname "$0")/.." && pwd)"
name="${1:?usage: $0 <example>}"
[[ -x "$examples/$name/e2e.sh" ]] || { echo "fail: $examples/$name/e2e.sh not found" >&2; exit 1; }
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
cp -R "$examples/$name" "$work/$name"
cp -R "$examples/ci" "$work/ci"
rm -rf "$work/$name/vendor" "$work/$name/composer.lock" "$work/$name/temp" "$work/$name/log"
"$examples/ci/use-local-nette-api.sh" "$work/$name"
"$work/$name/e2e.sh"
