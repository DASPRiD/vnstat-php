#!/bin/bash
# Renders the front page against each captured fixture and fails on any PHP
# diagnostic in the output.
#
# public/index.php is a script rather than a class, so PHPUnit never loads it
# and every formatter and the whole chart block go unchecked without this.
set -euo pipefail

cd "$(dirname "$0")/.."

port=8099
workdir=$(mktemp -d)
trap 'rm -rf "$workdir"; [ -n "${server_pid:-}" ] && kill "$server_pid" 2>/dev/null' EXIT

# Stands in for the real binary so the page can be rendered without a vnStat
# database. The fixture is named by a pointer file rather than an environment
# variable, because the stub runs as a child of the server process and never
# sees the environment of whatever is driving the requests.
printf '#!/bin/sh\nexec cat "$(cat %s)"\n' "$workdir/current" > "$workdir/vnstat"
chmod +x "$workdir/vnstat"
export PATH="$workdir:$PATH"

php -d error_reporting=E_ALL -d display_errors=1 -d html_errors=0 \
    -S "127.0.0.1:$port" -t public >"$workdir/server.log" 2>&1 &
server_pid=$!

ready=0

for _ in $(seq 1 40); do
    if ! kill -0 "$server_pid" 2>/dev/null; then
        echo 'FAIL: the built-in server exited during startup'
        cat "$workdir/server.log"
        exit 1
    fi

    if php -r "exit(@fsockopen('127.0.0.1', $port) ? 0 : 1);"; then
        ready=1
        break
    fi

    sleep 0.25
done

if [ "$ready" -ne 1 ]; then
    printf 'FAIL: nothing listening on port %s after ten seconds\n' "$port"
    cat "$workdir/server.log"
    exit 1
fi

fetch() {
    realpath "$1" > "$workdir/current"
    php -r '
        // ignore_errors keeps the body of a 4xx or 5xx response, which is the
        // half of this script that checks the error path.
        $context = stream_context_create(["http" => ["ignore_errors" => true]]);
        $body = @file_get_contents("http://127.0.0.1:" . getenv("PORT") . "/", false, $context);
        preg_match("{ (\d{3}) }x", $http_response_header[0] ?? "", $match);
        echo ($match[1] ?? "000"), "\n", $body;
    '
}

export PORT="$port"
status=0

check() {
    local fixture="$1" expected="$2" expected_text="$3" min_rows="${4:-0}"
    local response code body rows

    response=$(fetch "$fixture")
    code=${response%%$'\n'*}
    body=${response#*$'\n'}

    if [ "$code" != "$expected" ]; then
        printf 'FAIL %s: expected HTTP %s, got %s\n' "$fixture" "$expected" "$code"
        status=1
        return
    fi

    if printf '%s' "$body" | grep -qE '(Deprecated|Warning|Notice|Fatal error|Parse error):'; then
        printf 'FAIL %s: PHP diagnostic in output\n' "$fixture"
        printf '%s\n' "$body" | grep -E '(Deprecated|Warning|Notice|Fatal error|Parse error):'
        status=1
        return
    fi

    if ! printf '%s' "$body" | grep -q "$expected_text"; then
        printf 'FAIL %s: expected to find %s\n' "$fixture" "$expected_text"
        status=1
        return
    fi

    rows=$(printf '%s' "$body" | grep -c '<tr>' || true)

    if [ "$rows" -lt "$min_rows" ]; then
        printf 'FAIL %s: expected at least %s table rows, found %s\n' "$fixture" "$min_rows" "$rows"
        status=1
        return
    fi

    if [ "$min_rows" -gt 0 ] && ! printf '%s' "$body" | grep -qE '[0-9] [KMGT]iB'; then
        printf 'FAIL %s: no formatted byte values, so the formatters never ran\n' "$fixture"
        status=1
        return
    fi

    printf 'ok   %s (HTTP %s)\n' "$fixture" "$code"
}

for fixture in tests/fixtures/*.json; do
    check "$fixture" 200 '<h2>Hourly</h2>' 40
done

# The catch in public/index.php is the only thing standing between a failed
# vnstat and a blank page, and no unit test reaches it.
printf 'Error: Failed to open database\n' > "$workdir/broken.txt"
check "$workdir/broken.txt" 500 'Expected vnStat JSON'

# Diagnostics reach the server log whatever display_errors and html_errors are
# set to, so this catches anything the response body check misses.
if grep -qE '(Deprecated|Warning|Notice|Fatal error|Parse error):' "$workdir/server.log"; then
    echo 'FAIL: PHP diagnostic in the server log'
    grep -E '(Deprecated|Warning|Notice|Fatal error|Parse error):' "$workdir/server.log"
    status=1
fi

exit "$status"
