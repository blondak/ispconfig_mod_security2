#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for test in run diagnostics diagnostic_module hooks store access i18n server_config regex compatibility; do php "tests/$test.php"; done
source_dir=${1:-/tmp/ispconfig3_install}
if [[ ! -f $source_dir/server/lib/classes/tpl.inc.php ]]; then
    mkdir -p "$source_dir"
    tar -xzf ISPConfig-3.3.3p2.tar.gz -C "$source_dir" --strip-components=1
fi
php tests/template.php "$source_dir"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find modsecurity tests -name '*.php' -print0)
for file in scripts/*.sh; do bash -n "$file"; done
./scripts/package-release.sh
bash tests/installer.sh
