#!/usr/bin/env bash
# Executed on each target over SSH, master first. Requires root.
set -euo pipefail
[[ $(id -u) == 0 ]] || { echo 'Deployment requires root on the target' >&2; exit 1; }
incoming=${1:?Missing upload directory}
version=${2:?Missing release version}
[[ $incoming =~ ^/tmp/ispconfig-modsecurity\.[a-zA-Z0-9]+$ && -d $incoming && ! -L $incoming ]] || exit 1
[[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 1
base=/usr/local/ispconfig/extensions
active=$base/modsecurity
mkdir -p "$base"
exec 9>"$base/.modsecurity-deploy.lock"
flock -n 9 || { echo 'Another deployment is running' >&2; exit 1; }
archive=$incoming/modsecurity-$version.tar.gz
(cd "$incoming" && sha256sum -c "modsecurity-$version.tar.gz.sha256")
# A release contains only regular files/directories beneath modsecurity/.
tar --absolute-names --quoting-style=escape -tzf "$archive" > "$incoming/entries"
while IFS= read -r entry; do
    [[ $entry =~ ^modsecurity(/[A-Za-z0-9_.-]+)*/?$ && ! $entry =~ (^|/)\.\.?(/|$) ]] || { echo "Unsafe archive path: $entry" >&2; exit 1; }
done < "$incoming/entries"
tar --absolute-names --quoting-style=escape -tvzf "$archive" > "$incoming/types"
while IFS= read -r entry; do
    [[ ${entry:0:1} == - || ${entry:0:1} == d ]] || { echo 'Release archive may contain only regular files and directories' >&2; exit 1; }
done < "$incoming/types"
stage=$(mktemp -d "$base/.modsecurity-stage.XXXXXXXX")
backup=$base/.modsecurity-backup-$(date -u +%Y%m%dT%H%M%SZ)-$$
had_previous=false
switched=false
cleanup() {
    code=$?
    trap - EXIT
    if [[ $code != 0 && $switched == true ]]; then
        echo 'Deployment failed; restoring previous code' >&2
        # Remove only extension-owned copied files/symlinks before restoring the old list.
        php "$active/install/local.php" --disable-files || true
        rm -rf "$active"
        if [[ $had_previous == true ]]; then
            mv "$backup" "$active"
            php "$active/install/local.php" --enable-files || true
        fi
    fi
    rm -rf "$stage" "$incoming"
    exit "$code"
}
trap cleanup EXIT
tar -xzf "$archive" -C "$stage" --no-same-owner --no-same-permissions
[[ $(cat "$stage/modsecurity/VERSION") == "$version" ]] || { echo 'Archive version mismatch' >&2; exit 1; }
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$stage/modsecurity" -name '*.php' -print0)
/usr/sbin/apache2ctl configtest
mode=--install
if [[ -d $active ]]; then
    [[ ! -L $active ]] || { echo 'Expected a regular extension directory' >&2; exit 1; }
    had_previous=true
    if [[ -f $active/VERSION ]]; then mode=--update-local; fi
    if [[ -f $active/dbversion ]]; then cp "$active/dbversion" "$stage/modsecurity/dbversion"; fi
    mv "$active" "$backup"
fi
switched=true
mv "$stage/modsecurity" "$active"
chown -R root:root "$active"
chmod -R u=rwX,go=rX "$active"
php "$active/install/local.php" "$mode"
/usr/sbin/apache2ctl configtest
printf 'Deployed ModSecurity %s; previous code: %s\n' "$version" "$backup"
# Installation adds hooks; initial website Resync is an explicit ISPConfig admin action.
