#!/usr/bin/env bash
# All network responses are local fixtures. No installation or root access needed.
set -euo pipefail
cd "$(dirname "$0")/.."
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
mkdir -p "$work/bin" "$work/release"
cp dist/modsecurity-0.1.0.tar.gz{,.sha256} dist/deploy-remote.sh{,.sha256} "$work/release/"
cat > "$work/release/release.json" <<'JSON'
{"tag_name":"v0.1.0","draft":false,"prerelease":false}
JSON
cat > "$work/bin/curl" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
[[ " $* " == *' --proto =https '* && " $* " == *' --proto-redir =https '* ]] || exit 90
output='';url=${!#}
while [[ $# -gt 0 ]]; do
    case "$1" in --output) output=$2;shift 2;; *) shift;; esac
done
printf '%s\n' "$url" >> "$FIXTURE_REQUESTS"
[[ ${FIXTURE_FAIL:-false} == false ]] || exit 22
case "$url" in
    https://api.github.com/repos/blondak/ispconfig_mod_security2/releases/latest|https://api.github.com/repos/blondak/ispconfig_mod_security2/releases/tags/v0.1.0)
        cp "$FIXTURE_DIR/release.json" "$output";;
    https://github.com/blondak/ispconfig_mod_security2/releases/download/v0.1.0/*)
        file=${url##*/};[[ -f $FIXTURE_DIR/$file ]] || exit 22
        cp "$FIXTURE_DIR/$file" "$output";;
    *) exit 91;;
esac
MOCK
chmod 755 "$work/bin/curl"
export PATH="$work/bin:$PATH" FIXTURE_DIR="$work/release" FIXTURE_REQUESTS="$work/requests"
count=0
pass() { count=$((count+1));printf 'PASS %s\n' "$*"; }
reject() {
    if bash scripts/install.sh "$@" > "$work/result" 2>&1;then cat "$work/result";echo 'Expected installer rejection' >&2;exit 1;fi
}
bash scripts/install.sh --help > "$work/result"
[[ ! -f $work/requests ]] && pass 'help does not access the network'
bash scripts/install.sh --check > "$work/result"
grep -q 'Release 0.1.0 downloads and checksums verified' "$work/result"
[[ $(wc -l < "$work/requests") == 5 ]] && pass 'latest release and both verified assets downloaded over HTTPS'
: > "$work/requests"
bash scripts/install.sh --version v0.1.0 --check > "$work/result"
grep -q '/releases/tags/v0.1.0$' "$work/requests" && pass 'explicit version uses the matching release metadata'
reject --version '../../main' --check
pass 'invalid version rejected'
reject --unknown
pass 'unknown arguments rejected'
bash -s -- --version 0.1.0 --check < scripts/install.sh > "$work/result"
grep -q 'Release 0.1.0 downloads and checksums verified' "$work/result" && pass 'stdin invocation supports bash -s -- arguments'
cp "$work/release/modsecurity-0.1.0.tar.gz" "$work/original.tar.gz"
printf tampered >> "$work/release/modsecurity-0.1.0.tar.gz"
reject --check
grep -q FAILED "$work/result" && pass 'tampered archive rejected'
cp "$work/original.tar.gz" "$work/release/modsecurity-0.1.0.tar.gz"
cp "$work/release/deploy-remote.sh" "$work/original.sh"
printf '\n# changed\n' >> "$work/release/deploy-remote.sh"
reject --check
grep -q FAILED "$work/result" && pass 'tampered deployment backend rejected'
cp "$work/original.sh" "$work/release/deploy-remote.sh"
cp "$work/release/deploy-remote.sh.sha256" "$work/original.sha256"
sed 's/  deploy-remote.sh/  ..\/outside/' "$work/original.sha256" > "$work/release/deploy-remote.sh.sha256"
reject --check
grep -q 'Unexpected checksum filename' "$work/result" && pass 'checksum cannot reference an unrelated filesystem path'
cp "$work/original.sha256" "$work/release/deploy-remote.sh.sha256"
cat "$work/original.sha256" >> "$work/release/deploy-remote.sh.sha256"
reject --check
grep -q 'Expected one checksum' "$work/result" && pass 'multiple checksum entries rejected'
cp "$work/original.sha256" "$work/release/deploy-remote.sh.sha256"
for kind in draft prerelease;do
    printf '{"tag_name":"v0.1.0","draft":false,"prerelease":false,"%s":true}\n' "$kind" > "$work/release/release.json"
    : > "$work/requests"
    reject --check
    [[ $(wc -l < "$work/requests") == 1 ]] && pass "$kind release rejected before asset download"
done
printf '{"tag_name":"v0.2.0","draft":false,"prerelease":false}\n' > "$work/release/release.json"
reject --version 0.1.0 --check
grep -q 'Release version mismatch' "$work/result" && pass 'release metadata must match the requested version'
export FIXTURE_FAIL=true
reject --check
grep -q 'Cannot fetch release metadata' "$work/result" && pass 'unpublished or unavailable release fails clearly'
echo "$count online installer fixture checks passed"
