#!/usr/bin/env bash
# Run with the PHP interpreter under test, including legacy official CLI images.
set -euo pipefail
cd "$(dirname "$0")/.."
php --version
while IFS= read -r -d '' file; do
    if ! result=$(php -l "$file" 2>&1);then printf '%s\n' "$result" >&2;exit 1;fi
done < <(find modsecurity -name '*.php' -print0)
for test in run diagnostics diagnostic_module hooks store access i18n server_config regex compatibility;do php "tests/$test.php";done
