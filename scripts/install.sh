#!/usr/bin/env bash
# Online installer for the public blondak/ispconfig_mod_security2 releases.
set -euo pipefail
repository=blondak/ispconfig_mod_security2
requested=latest
check_only=false
usage() {
    cat <<'HELP'
Usage: bash install.sh [--version X.Y.Z] [--check]

Download a published stable GitHub Release, verify SHA-256 checksums and install
or update the ISPConfig ModSecurity extension on this machine.

  --version X.Y.Z   Select a release (vX.Y.Z is also accepted); default: latest
  --check           Download and verify checksums without installing; no root needed
  --help            Show this help

Installation requires root, Debian 12, ISPConfig >= 3.3.3p2, Apache with
ModSecurity 2, PHP >= 7.4 CLI (panel integration needs PHP >= 7.4), mysqli/mbstring/posix, curl and GNU tar.
PHP 8.2 is recommended on Debian 12.
Run on the master first, then on each web node using the same version.
After the first installation, Resync websites in ISPConfig and sign in again.
HELP
}
fail() { printf 'Error: %s\n' "$*" >&2; exit 1; }
while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            [[ $# -ge 2 ]] || fail '--version requires X.Y.Z'
            requested=${2#v};shift 2 ;;
        --check) check_only=true;shift ;;
        --help|-h) usage;exit 0 ;;
        *) fail "Unknown option: $1" ;;
    esac
done
[[ $requested == latest || $requested =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail 'Expected version X.Y.Z'
for command in curl php tar sha256sum mktemp rm bash; do
    command -v "$command" >/dev/null || fail "Required command not found: $command"
done
php -r 'if(PHP_VERSION_ID<70400) { fwrite(STDERR,"PHP 7.4 or newer is required.\n");exit(1); }'
if [[ $check_only == false ]]; then
    [[ $(id -u) == 0 ]] || fail 'Run installation as root, or use --check to verify downloads only'
    [[ -f /etc/os-release ]] || fail 'Cannot identify the operating system'
    # shellcheck disable=SC1091
    source /etc/os-release
    [[ ${ID:-} == debian && ${VERSION_ID:-} == 12 ]] || fail 'This installer targets Debian 12'
    for command in flock find chown chmod mv cp date grep sed runuser; do
        command -v "$command" >/dev/null || fail "Required command not found: $command"
    done
    [[ -f /usr/local/ispconfig/server/lib/config.inc.php && -f /usr/local/ispconfig/server/lib/app.inc.php ]] || fail 'ISPConfig is not installed at /usr/local/ispconfig'
    php <<'PHP'
<?php
if(PHP_VERSION_ID<70400) { fwrite(STDERR,"PHP 7.4 or newer is required for the installer.\n");exit(1); }
foreach(['mysqli','mbstring','posix'] as $module) {
    if(!extension_loaded($module)) { fwrite(STDERR,"Required PHP extension not found: $module\n");exit(1); }
}
$config=file_get_contents('/usr/local/ispconfig/server/lib/config.inc.php');
if(!preg_match('/define\(\s*[\'"]ISPC_APP_VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',$config,$match)
    || version_compare($match[1],'3.3.3p2','<')) {
    fwrite(STDERR,"ISPConfig 3.3.3p2 or newer is required.\n");exit(1);
}
echo 'ISPConfig '.$match[1]." detected\n";
PHP
    [[ -x /usr/sbin/apache2ctl ]] || fail 'Apache is required on this target'
    modules=$(/usr/sbin/apache2ctl -M 2>&1) || fail 'Apache configuration test failed'
    [[ $modules == *security2_module* ]] || fail 'Enable the Apache security2 module before installing'
    # Check the actual interpreter used by the standard ISPConfig CGI wrapper.
    # Never execute the wrapper as root: it starts the web handler and may be custom.
    panel_wrapper=/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter
    panel_vhost=/etc/apache2/sites-enabled/000-ispconfig.vhost
    if [[ $modules == *fcgid_module* && -r $panel_wrapper && -r $panel_vhost ]] && grep -Fq "$panel_wrapper" "$panel_vhost"; then
        panel_binary=$(sed -nE 's|^[[:space:]]*exec[[:space:]]+(/[a-zA-Z0-9_./+-]+).*|\1|p' "$panel_wrapper")
        [[ $panel_binary =~ ^/[a-zA-Z0-9_./+-]+$ && -x $panel_binary ]] || fail 'Cannot identify the panel CGI interpreter; inspect its wrapper'
        panel_output=$(runuser -u ispconfig -- "$panel_binary" -n -v </dev/null) || fail 'Cannot query the panel CGI interpreter as ispconfig'
        panel_version=$(printf '%s\n' "$panel_output" | sed -nE 's/^PHP ([0-9]+\.[0-9]+\.[0-9]+).*/\1/p')
        [[ $panel_version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail 'Cannot identify the panel CGI PHP version'
        php -r 'if(version_compare($argv[1],"7.4.0","<")) { fwrite(STDERR,"WARNING: Panel PHP 7.4 or newer is required; detected ".$argv[1].". Panel integration will remain disabled.\n"); }' "$panel_version"
        printf 'Panel CGI PHP %s detected (%s)\n' "$panel_version" "$panel_binary"
    else
        printf 'Panel PHP is not automatically detected for this handler; verify PHP >= 7.4 separately.\n'
    fi
fi
umask 077
work=$(mktemp -d /tmp/ispconfig-modsecurity.XXXXXXXX)
trap 'rm -rf "$work"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
fetch() {
    curl --fail --silent --show-error --location --proto '=https' --proto-redir '=https' \
        --connect-timeout 15 --max-time 180 --retry 2 \
        --header 'Accept: application/vnd.github+json' \
        --user-agent 'ispconfig-modsecurity-installer' --output "$2" "$1"
}
endpoint=https://api.github.com/repos/$repository/releases/latest
if [[ $requested != latest ]]; then endpoint=https://api.github.com/repos/$repository/releases/tags/v$requested;fi
printf 'Resolving %s release from %s...\n' "$requested" "$repository"
fetch "$endpoint" "$work/release.json" || fail 'Cannot fetch release metadata. A published release with installer assets is required'
version=$(php -r '
try {
    $release=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
    if(($release["draft"]??true)!==false || ($release["prerelease"]??true)!==false || !preg_match("/^v([0-9]+\\.[0-9]+\\.[0-9]+)$/D",$release["tag_name"]??"",$match)) throw new RuntimeException("Expected a published stable vX.Y.Z release");
    if($argv[2]!=="latest" && $match[1]!==$argv[2]) throw new RuntimeException("Release version mismatch");
    echo $match[1];
} catch(Throwable $error) { fwrite(STDERR,$error->getMessage()."\n");exit(1); }
' "$work/release.json" "$requested")
[[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail 'Invalid release version'
if [[ $check_only == false && -f /usr/local/ispconfig/extensions/modsecurity/VERSION ]]; then
    installed=$(cat /usr/local/ispconfig/extensions/modsecurity/VERSION)
    [[ $installed =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail 'Installed extension has an invalid VERSION file'
    php -r 'if(version_compare($argv[1],$argv[2],">")) { fwrite(STDERR,"Downgrading is not supported.\n");exit(1); }' "$installed" "$version"
fi
base=https://github.com/$repository/releases/download/v$version
verify() {
    local file=$1 line digest name
    local -a lines
    mapfile -t lines < "$work/$file.sha256"
    [[ ${#lines[@]} == 1 ]] || fail "Expected one checksum for $file"
    line=${lines[0]}
    [[ $line =~ ^([a-fA-F0-9]{64})[[:space:]]+\*?([^[:space:]]+)$ ]] || fail "Invalid checksum for $file"
    digest=${BASH_REMATCH[1]};name=${BASH_REMATCH[2]}
    [[ $name == "$file" ]] || fail "Unexpected checksum filename for $file"
    (cd "$work" && printf '%s  %s\n' "$digest" "$file" | sha256sum --check --strict -)
}
for file in "modsecurity-$version.tar.gz" deploy-remote.sh; do
    printf 'Downloading %s...\n' "$file"
    fetch "$base/$file" "$work/$file" || fail "Cannot download $file from the release"
    fetch "$base/$file.sha256" "$work/$file.sha256" || fail "Cannot download the checksum for $file"
    verify "$file"
done
bash -n "$work/deploy-remote.sh"
if [[ $check_only == true ]]; then
    printf 'Release %s downloads and checksums verified. Nothing was installed.\n' "$version"
    exit 0
fi
printf 'Installing ModSecurity extension %s...\n' "$version"
bash "$work/deploy-remote.sh" "$work" "$version"
printf 'Installation completed: %s. After initial installation, run Tools > Resync > Websites and sign in again.\n' "$version"
