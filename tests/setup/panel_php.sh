#!/usr/bin/env bash
# Exercise standard CGI preflight with simulated version output in the disposable lab.
set -euo pipefail
[[ ${LAB_SERVER_ID:-} == 1 && $(id -u) == 0 && -f /lab/bootstrap.php ]] || exit 1
installer=${1:?Path to the online installer}
work=$(mktemp -d)
wrapper=/var/www/php-fcgi-scripts/ispconfig/.php-fcgi-starter
vhost=/etc/apache2/sites-enabled/000-ispconfig.vhost
[[ ! -e $wrapper && ! -e $vhost ]] || { rm -rf "$work";exit 1; }
cleanup() {
    if [[ -f $work/apache2ctl ]];then cp -p "$work/apache2ctl" /usr/sbin/apache2ctl;fi
    rm -f "$wrapper" "$vhost"
    rm -rf "$work"
}
trap cleanup EXIT
chmod 755 "$work"
cp -p /usr/sbin/apache2ctl "$work/apache2ctl"
export FIXTURE_APACHE_CTL="$work/apache2ctl"
cat > /usr/sbin/apache2ctl <<'MOCK'
#!/bin/sh
if [ "$1" = -M ];then
    "$FIXTURE_APACHE_CTL" "$@" || exit 1
    printf ' fcgid_module (shared)\n'
else exec "$FIXTURE_APACHE_CTL" "$@";fi
MOCK
mkdir -p "$(dirname "$wrapper")"
printf 'exec %s/panel-php\n' "$work" > "$wrapper"
printf '# CGI preflight fixture: %s\n' "$wrapper" > "$vhost"
printf '#!/bin/sh\nprintf "PHP 7.3.33 (cgi-fcgi)\\n"\n' > "$work/panel-php"
chmod 755 "$work/panel-php"
bash "$installer" --version 0.1.0 > "$work/output" 2>&1
grep -q 'WARNING: Panel PHP 7.4 or newer is required; detected 7.3.33. Panel integration will remain disabled.' "$work/output"
echo 'PASS panel preflight warns about old CGI PHP independently of CLI PHP'
printf '#!/bin/sh\nprintf "PHP 7.4.33 (cgi-fcgi)\\n"\n' > "$work/panel-php"
bash "$installer" --version 0.1.0 > "$work/output" 2>&1
grep -q 'Panel CGI PHP 7.4.33 detected' "$work/output"
echo 'PASS panel preflight accepts the minimum CGI PHP version'
