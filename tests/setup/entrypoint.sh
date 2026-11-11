#!/bin/bash
set -euo pipefail
if [ -d /lab-extension ]; then cp -a /lab-extension/. /usr/local/ispconfig/extensions/modsecurity/; fi
php /lab/bootstrap.php
mkdir -p /usr/local/ispconfig/server/{mods-enabled,plugins-enabled,mods-available} /etc/apache2/ispconfig-modsecurity
php /usr/local/ispconfig/extensions/modsecurity/install/local.php
ln -sf /usr/local/ispconfig/extensions/modsecurity/server/modsecurity_module.inc.php /usr/local/ispconfig/server/mods-available/modsecurity_module.inc.php
chown -R www-data:www-data /usr/local/ispconfig/interface/cache /usr/local/ispconfig/interface/web/temp
usermod -a -G ispconfig www-data
touch /var/log/ispconfig/auth.log
chown www-data:www-data /var/log/ispconfig/auth.log
sid="$LAB_SERVER_ID"
domain="web${sid}.lab.test"
root="/var/www/clients/client${sid}/web${sid}"
mkdir -p "$root/web/admin" "/var/log/ispconfig/httpd/$domain"
ln -sfn "/var/log/ispconfig/httpd/$domain" "$root/log"
echo "Web client $sid / server $sid" > "$root/web/index.html"
echo ok > "$root/web/admin/editor"
cp /lab/error-sample.log "/var/log/ispconfig/httpd/$domain/error.log"
sed -i "s/web1.lab.test/$domain/g; s/lab-request-/lab-server${sid}-request-/g" "/var/log/ispconfig/httpd/$domain/error.log"
chmod 640 "/var/log/ispconfig/httpd/$domain/error.log"
cat > /etc/apache2/sites-available/ispconfig.vhost <<'EOF'
<VirtualHost *:80>
 ServerName 127.0.0.1
 DocumentRoot /usr/local/ispconfig/interface/web
 SecRuleEngine Off
 # Mirror a restricted production panel: UI code must stay within interface.
 php_admin_value open_basedir "/usr/local/ispconfig/interface:/usr/local/ispconfig/security:/usr/share:/tmp"
 <Directory /usr/local/ispconfig/interface/web>
  Require all granted
  AllowOverride All
  DirectoryIndex index.php
 </Directory>
</VirtualHost>
EOF
ln -sf ../sites-available/ispconfig.vhost /etc/apache2/sites-enabled/000-ispconfig.conf
cat > "/etc/apache2/sites-available/$domain.vhost" <<EOF
<VirtualHost *:8081>
 ServerName $domain
 DocumentRoot $root/web
 ErrorLog /var/log/ispconfig/httpd/$domain/error.log
 IncludeOptional /etc/apache2/ispconfig-modsecurity/web$sid.conf
 <Directory $root/web>
  Require all granted
 </Directory>
</VirtualHost>
EOF
ln -sf "../sites-available/$domain.vhost" "/etc/apache2/sites-enabled/100-web.conf"
apache2ctl -D FOREGROUND &
apache=$!
for attempt in $(seq 1 30); do
 if curl --fail --silent http://127.0.0.1/login/ >/dev/null; then break; fi
 sleep 1
done
php /lab/worker.php --restore
(while true; do php /lab/worker.php || true; sleep 2; done) &
worker=$!
trap 'kill "$worker" 2>/dev/null || true; apache2ctl stop || true' TERM INT
wait "$apache"
