#!/usr/bin/env bash
# Called by Actions; all configuration arrives through environment variables.
set -euo pipefail
: "${RELEASE_TAG:?}" "${GH_REPO:?}" "${DEPLOY_HOSTS:?}" "${DEPLOY_SSH_KEY:?}" "${DEPLOY_KNOWN_HOSTS:?}"
version=${RELEASE_TAG#v}
[[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 1
user=${DEPLOY_USER:-root}
port=${DEPLOY_PORT:-22}
[[ $user =~ ^[a-z_][a-z0-9_-]*$ && $port =~ ^[0-9]+$ && $port -ge 1 && $port -le 65535 ]] || exit 1
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
umask 077
printf '%s\n' "$DEPLOY_SSH_KEY" > "$work/key"
printf '%s\n' "$DEPLOY_KNOWN_HOSTS" > "$work/known_hosts"
unset DEPLOY_SSH_KEY DEPLOY_KNOWN_HOSTS
opts=(-i "$work/key" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$work/known_hosts" -o ConnectTimeout=20)
gh release download "$RELEASE_TAG" --repo "$GH_REPO" --dir "$work" --pattern "modsecurity-$version.tar.gz" --pattern "modsecurity-$version.tar.gz.sha256"
(cd "$work" && sha256sum -c "modsecurity-$version.tar.gz.sha256")
count=0
while IFS= read -r host || [[ -n $host ]]; do
    host=${host%$'\r'}
    [[ -z $host ]] && continue
    [[ $host =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*$ ]] || { echo 'Use one DNS name or IPv4 address per DEPLOY_HOSTS line' >&2; exit 1; }
    target=$user@$host
    incoming=$(ssh "${opts[@]}" -p "$port" "$target" 'mktemp -d /tmp/ispconfig-modsecurity.XXXXXXXX')
    [[ $incoming =~ ^/tmp/ispconfig-modsecurity\.[a-zA-Z0-9]+$ ]] || exit 1
    scp "${opts[@]}" -P "$port" "$work/modsecurity-$version.tar.gz" "$work/modsecurity-$version.tar.gz.sha256" "$target:$incoming/"
    ssh "${opts[@]}" -p "$port" "$target" bash -s -- "$incoming" "$version" < scripts/deploy-remote.sh
    count=$((count+1))
done <<< "$DEPLOY_HOSTS"
[[ $count -gt 0 ]] || { echo 'No deployment hosts configured' >&2; exit 1; }
