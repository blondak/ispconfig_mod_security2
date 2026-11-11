#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
version=${1:-$(cat modsecurity/VERSION)}
version=${version#v}
[[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Expected version X.Y.Z' >&2; exit 1; }
[[ $version == "$(cat modsecurity/VERSION)" ]] || { echo 'Tag and modsecurity/VERSION must match' >&2; exit 1; }
mkdir -p dist
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
cp -a modsecurity "$stage/modsecurity"
cp README.md LICENSE "$stage/modsecurity/"
# Runtime state never belongs in a release; deployment preserves the schema marker.
rm -f "$stage/modsecurity/dbversion"
epoch=${SOURCE_DATE_EPOCH:-0}
[[ $epoch =~ ^[0-9]+$ ]] || exit 1
tar --sort=name --mtime="@$epoch" --owner=0 --group=0 --numeric-owner -czf "dist/modsecurity-$version.tar.gz" -C "$stage" modsecurity
(cd dist && sha256sum "modsecurity-$version.tar.gz" > "modsecurity-$version.tar.gz.sha256")
printf 'Built dist/modsecurity-%s.tar.gz\n' "$version"
