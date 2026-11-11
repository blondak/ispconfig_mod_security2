FROM debian:12-slim
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends apache2 libapache2-mod-php8.2 php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-gd php8.2-zip php8.2-intl mariadb-client libapache2-mod-security2 modsecurity-crs curl ca-certificates procps && rm -rf /var/lib/apt/lists/*
COPY ISPConfig-3.3.3p2.tar.gz /tmp/ispconfig.tar.gz
RUN mkdir -p /opt/ispc-source /usr/local/ispconfig/extensions && tar -xzf /tmp/ispconfig.tar.gz -C /opt/ispc-source --strip-components=1 && cp -a /opt/ispc-source/interface /opt/ispc-source/server /usr/local/ispconfig/ && rm /tmp/ispconfig.tar.gz && useradd --system --home-dir /usr/local/ispconfig ispconfig && mkdir -p /usr/local/ispconfig/security /var/log/ispconfig /usr/local/ispconfig/interface/cache /usr/local/ispconfig/interface/web/temp && cp /opt/ispc-source/install/tpl/security_settings.ini.master /usr/local/ispconfig/security/security_settings.ini
COPY modsecurity /usr/local/ispconfig/extensions/modsecurity
COPY tests/setup /lab
RUN a2enmod security2 rewrite headers && a2dissite 000-default && cp /etc/modsecurity/modsecurity.conf-recommended /etc/modsecurity/modsecurity.conf && sed -i 's/SecRuleEngine DetectionOnly/SecRuleEngine On/' /etc/modsecurity/modsecurity.conf && printf 'ServerName localhost\nListen 8081\n' > /etc/apache2/conf-available/lab.conf && a2enconf lab && chmod +x /lab/entrypoint.sh
# Test container has no systemd. This adapter really performs the requested Apache reload.
RUN printf '#!/bin/sh\nif [ "$1" = reload ] && [ "$2" = apache2 ]; then exec /usr/sbin/apache2ctl graceful; fi\nexit 1\n' > /usr/bin/systemctl && chmod 755 /usr/bin/systemctl
ENTRYPOINT ["/lab/entrypoint.sh"]
