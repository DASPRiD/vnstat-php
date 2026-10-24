#!/bin/bash
# Compiles every PHP file and fails on any diagnostic, not just on a syntax
# error. "php -l" reports a deprecation and still exits 0, so the exit code
# alone would let one through.
set -uo pipefail

cd "$(dirname "$0")/.."

directories=(src public tests bin config)
status=0

mapfile -d '' files < <(find "${directories[@]}" \( -name '*.php' -o -name '*.php.dist' \) -print0)

# A find that matches nothing looks exactly like a clean run, so a renamed
# directory would turn this into a check of zero files.
if [ "${#files[@]}" -eq 0 ]; then
    echo 'No PHP files found.'
    exit 1
fi

for file in "${files[@]}"; do
    output=$(php -d error_reporting=E_ALL -d display_errors=1 -l "$file" 2>&1)

    if printf '%s\n' "$output" | grep -qE '^(PHP )?(Deprecated|Warning|Notice|Fatal error|Parse error):'; then
        printf '%s\n' "$output" | grep -vE '^(No syntax errors|$)'
        status=1
    fi
done

if [ "$status" -eq 0 ]; then
    printf 'No diagnostics in %s files.\n' "${#files[@]}"
fi

exit "$status"
