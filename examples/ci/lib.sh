#!/usr/bin/env bash
# Helpers for the e2e.sh scripts: start the PHP built-in server and assert on HTTP responses.
# Sourced, not run. Needs php and curl.

# serve [port]: starts php -S for public/ in the background and waits until it accepts connections.
# Can be called more than once (with different ports and environment), $BASE points to the last one.
SERVERS=()
serve() {
    PORT="${1:-8080}"
    BASE="http://127.0.0.1:$PORT"
    mkdir -p temp log
    PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$PORT" -t public public/index.php > "log/server-$PORT.log" 2>&1 &
    SERVERS+=("$!")
    trap 'kill "${SERVERS[@]}" 2>/dev/null || true' EXIT
    for _ in $(seq 1 50); do
        php -r 'exit(@fsockopen("127.0.0.1", (int) $argv[1]) ? 0 : 1);' "$PORT" 2>/dev/null && return 0
        sleep 0.1
    done
    echo "fail: the PHP web server did not start" >&2
    cat "log/server-$PORT.log" >&2
    exit 1
}

# request <curl args...>: runs curl, sets $STATUS, $BODY and $HEADERS
request() {
    local out
    out="$(mktemp)"
    HEADERS="$(curl -sS -D - -o "$out" -w '' "$@")"
    STATUS="$(printf '%s' "$HEADERS" | head -n1 | awk '{print $2}')"
    BODY="$(cat "$out")"
    rm -f "$out"
}

# expect_status <code> <name>
expect_status() {
    if [[ "$STATUS" != "$1" ]]; then
        echo "fail: $2: expected HTTP $1, got $STATUS" >&2
        echo "$BODY" >&2
        exit 1
    fi
}

# expect_body <fixed string> <name>
expect_body() {
    if [[ "$BODY" != *"$1"* ]]; then
        echo "fail: $2: body does not contain $1" >&2
        echo "$BODY" >&2
        exit 1
    fi
}

# expect_header <fixed string, case-insensitive> <name>
expect_header() {
    if ! printf '%s' "$HEADERS" | grep -qiF -- "$1"; then
        echo "fail: $2: headers do not contain $1" >&2
        printf '%s\n' "$HEADERS" >&2
        exit 1
    fi
}

# no_header <fixed string, case-insensitive> <name>
no_header() {
    if printf '%s' "$HEADERS" | grep -qiF -- "$1"; then
        echo "fail: $2: headers contain $1" >&2
        printf '%s\n' "$HEADERS" >&2
        exit 1
    fi
}

ok() {
    echo "ok: $1"
}
