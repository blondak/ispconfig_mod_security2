#!/bin/bash
set -euo pipefail
php /work/tests/apache/fixture.php
cp /etc/modsecurity/modsecurity.conf-recommended /etc/modsecurity/modsecurity.conf
sed -i 's/SecRuleEngine DetectionOnly/SecRuleEngine On/' /etc/modsecurity/modsecurity.conf
cat > /etc/modsecurity/zz-test.conf <<'EOF'
SecRule REQUEST_HEADERS:X-Test-Phase1 "@streq block" "id:10001,phase:1,deny,status:403,log"
SecRule REQUEST_HEADERS:X-Test-Phase2 "@streq block" "id:10002,phase:2,deny,status:403,log"
EOF
mkdir -p /var/www/html/admin
for f in normal exact exact-child off off-child admin/test; do echo ok > "/var/www/html/$f"; done
for path in livewire-006d7c12/update livewire-aF19/update livewire-xyz/update livewire-/update livewire-ab/update/extra prefix/livewire-ab/update v12/item.34 v12/itemX34 vdd/item.dd regex-off-123 regex-off-abc regex-off-456/extra; do
 mkdir -p "$(dirname "/var/www/html/$path")"
 echo ok > "/var/www/html/$path"
done
cat > /etc/apache2/sites-enabled/test.conf <<'EOF'
<VirtualHost *:80>
 ServerName managed.test
 DocumentRoot /var/www/html
 Include /etc/apache2/test-managed.conf
</VirtualHost>
<VirtualHost *:80>
 ServerName other.test
 DocumentRoot /var/www/html
</VirtualHost>
<VirtualHost *:80>
 ServerName off.test
 DocumentRoot /var/www/html
 Include /etc/apache2/test-Off.conf
</VirtualHost>
<VirtualHost *:80>
 ServerName detection.test
 DocumentRoot /var/www/html
 Include /etc/apache2/test-DetectionOnly.conf
</VirtualHost>
<VirtualHost *:80>
 ServerName excluded.test
 DocumentRoot /var/www/html
 Include /etc/apache2/test-site-exclusion.conf
</VirtualHost>
EOF
apache2ctl configtest
apache2ctl start
trap 'apache2ctl stop' EXIT
failures=0
check() {
 local want="$1" host="$2" path="$3" header="$4" got
 got=$(curl --silent --output /dev/null --write-out '%{http_code}' -H "Host: $host" -H "$header" "http://127.0.0.1$path")
 if [ "$got" = "$want" ]; then echo "PASS $host $path $header ($got)"; else echo "FAIL $host $path expected $want got $got"; failures=$((failures+1)); fi
}
echo "URL scope: phase 1 remains globally enforced; phase 2 exceptions apply. Full-site mode is configured at VirtualHost scope."
for phase in 1 2; do
 expected=200
 if [ "$phase" = 1 ]; then expected=403; fi
 check 200 off.test /normal "X-Test-Phase$phase: block"
 check 200 detection.test /normal "X-Test-Phase$phase: block"
 check 200 excluded.test /normal "X-Test-Phase$phase: block"
 check 403 managed.test /normal "X-Test-Phase$phase: block"
 check "$expected" managed.test /admin/test "X-Test-Phase$phase: block"
 check 403 other.test /admin/test "X-Test-Phase$phase: block"
 check "$expected" managed.test /off "X-Test-Phase$phase: block"
 check 403 managed.test /off-child "X-Test-Phase$phase: block"
done
check 200 managed.test /exact 'X-Test-Phase2: block'
check 403 managed.test /exact-child 'X-Test-Phase2: block'
check 200 managed.test /livewire-006d7c12/update 'X-Test-Phase2: block'
check 200 managed.test /livewire-aF19/update 'X-Test-Phase2: block'
check 403 managed.test /livewire-xyz/update 'X-Test-Phase2: block'
check 403 managed.test /livewire-/update 'X-Test-Phase2: block'
check 403 managed.test /livewire-ab/update/extra 'X-Test-Phase2: block'
check 403 managed.test /prefix/livewire-ab/update 'X-Test-Phase2: block'
check 403 managed.test /livewire-006d7c12/update 'X-Test-Phase1: block'
check 403 other.test /livewire-006d7c12/update 'X-Test-Phase2: block'
check 200 managed.test /v12/item.34 'X-Test-Phase2: block'
check 403 managed.test /v12/itemX34 'X-Test-Phase2: block'
check 403 managed.test /vdd/item.dd 'X-Test-Phase2: block'
check 200 managed.test /regex-off-123 'X-Test-Phase2: block'
check 403 managed.test /regex-off-123 'X-Test-Phase1: block'
check 403 managed.test /regex-off-abc 'X-Test-Phase2: block'
check 403 managed.test /regex-off-456/extra 'X-Test-Phase2: block'
# A response body match confirms Apache is actually serving the test fixture.
curl --fail --silent -H 'Host: managed.test' http://127.0.0.1/normal | grep -q '^ok'
test "$failures" -eq 0
