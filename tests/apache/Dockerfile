FROM debian:12-slim
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends apache2 libapache2-mod-security2 modsecurity-crs php-cli php-mbstring curl ca-certificates && rm -rf /var/lib/apt/lists/*
COPY modsecurity /work/modsecurity
COPY tests/apache /work/tests/apache
RUN a2enmod security2 && a2dissite 000-default
CMD ["bash", "/work/tests/apache/run.sh"]
