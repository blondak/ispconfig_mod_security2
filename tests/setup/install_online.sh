#!/usr/bin/env bash
# Destructive installation tests are restricted to the disposable server1 fixture.
set -euo pipefail
[[ ${LAB_SERVER_ID:-} == 1 && $(id -u) == 0 && -f /lab/bootstrap.php ]] || { echo 'Run only inside the disposable server1 lab container' >&2;exit 1; }
installer=${1:?Path to the online installer}
release=${2:?Path to copied release assets}
active=/usr/local/ispconfig/extensions/modsecurity
work=$(mktemp -d)
cp "$active/VERSION" "$work/original-version"
cleanup() {
    cp "$work/original-version" "$active/VERSION"
    rm -rf "$work"
}
trap cleanup EXIT
mkdir "$work/bin" "$work/release"
cp "$release"/modsecurity-0.1.0.tar.gz{,.sha256} "$release"/deploy-remote.sh{,.sha256} "$work/release/"
printf '{"tag_name":"v0.1.0","draft":false,"prerelease":false}\n' > "$work/release/release.json"
cat > "$work/bin/curl" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
url=${!#};output=''
while [[ $# -gt 0 ]];do case "$1" in --output) output=$2;shift 2;; *) shift;; esac;done
case "$url" in
    https://api.github.com/repos/blondak/ispconfig_mod_security2/releases/*) file=release.json;;
    https://github.com/blondak/ispconfig_mod_security2/releases/download/v0.1.0/*) file=${url##*/};;
    *) exit 22;;
esac
cp "$FIXTURE_DIR/$file" "$output"
MOCK
chmod 755 "$work/bin/curl"
export PATH="$work/bin:$PATH" FIXTURE_DIR="$work/release"
before=$(sha256sum "$active/lib/Config.php")
# Zero is the current schema version; the deployer must retain the marker.
printf '0\n' > "$active/dbversion"
bash "$installer" --version 0.1.0
[[ $(sha256sum "$active/lib/Config.php") == "$before" && $(cat "$active/dbversion") == 0 ]]
echo 'PASS online entry point installs a verified release and preserves the schema marker'
printf '99.0.0\n' > "$active/VERSION"
if bash "$installer" --version 0.1.0 > "$work/rejected" 2>&1;then echo 'Downgrade unexpectedly succeeded' >&2;exit 1;fi
grep -q 'Downgrading is not supported' "$work/rejected"
cp "$work/original-version" "$active/VERSION"
echo 'PASS online installer rejects a downgrade before replacing code'
mkdir "$work/broken"
tar -xzf "$work/release/modsecurity-0.1.0.tar.gz" -C "$work/broken"
php -r '$file=$argv[1];$source=file_get_contents($file);$source=str_replace("public function enable() {", "public function enable() { throw new RuntimeException(\"Expected online installer failure\");", $source);file_put_contents($file,$source);' "$work/broken/modsecurity/install/installer.php"
tar -czf "$work/release/modsecurity-0.1.0.tar.gz" -C "$work/broken" modsecurity
(cd "$work/release" && sha256sum modsecurity-0.1.0.tar.gz > modsecurity-0.1.0.tar.gz.sha256)
if bash "$installer" --version 0.1.0 > "$work/rejected" 2>&1;then echo 'Broken release unexpectedly succeeded' >&2;exit 1;fi
grep -q 'restoring previous code' "$work/rejected"
[[ $(sha256sum "$active/lib/Config.php") == "$before" && $(cat "$active/dbversion") == 0 ]]
! grep -q 'Expected online installer failure' "$active/install/installer.php"
/usr/sbin/apache2ctl configtest
echo 'PASS online installer restores working code after failed installation'
echo '3 online installation checks passed in the two-node lab'
