# Two-node ISPConfig 3.3.3p2 lab

Docker Compose runs MariaDB and two Debian 12 / Apache / ModSecurity nodes. The first node serves the real ISPConfig panel and website 1; the second serves website 2. Separate database schemas and the actual ISPConfig `sys_datalog` worker provide replication.

```sh
cd tests/setup
docker compose up -d --build
```

Panel: **http://127.0.0.1:18080**

| Login | Password | Websites |
|---|---|---|
| admin | Admin-Lab-123! | Both |
| client1 | Client1-Lab-123! | web1.lab.test, server1 |
| client2 | Client2-Lab-123! | web2.lab.test, server2 |

Open **Sites → Websites → website → ModSecurity** for Settings/Diagnostics. Open **System → Server Config → server → ModSecurity** for server defaults. Client management is controlled in **Clients → client → Limits → ModSecurity**; fresh clients default to read-only. `smoke.php` enables client1 and leaves client2 read-only to demonstrate both states.

The default account language is English. The smoke test temporarily changes its administrator account to German and Czech, signs in and verifies actual pages, then restores English.

In Diagnostics, request collection and refresh after a few seconds. Seed logs contain a summary-only block, an XSS block with 941100 and a separate 942100 warning. The lab worker runs every two seconds.

Websites: http://127.0.0.1:18081 and http://127.0.0.1:18082, using Host headers `web1.lab.test` / `web2.lab.test`. Actual CRS rules can generate additional blocks.

```sh
docker compose run --rm --no-deps --entrypoint php server1 /lab/smoke.php
docker compose run --rm --no-deps --entrypoint php server1 /lab/server_settings.php
docker compose run --rm --no-deps --entrypoint php server1 /lab/stale_session.php
```

CI additionally copies the release assets and `scripts/install.sh` into server1 and runs `install_online.sh` there. It supplies fixture GitHub responses and performs real installation/recovery in that disposable node.

Run these in order. The server test uses configurations saved by the smoke test. It verifies different server modes with actual XSS requests, then restores inherited server defaults. The session regression modifies only a session created by that test.

This lab covers extension components rather than a complete hosting installation. It does not run mail, DNS or FTP daemons or general hosting provisioning plugins. DNS server metadata exists so native client Limits selectors validate. Vhosts are created by the fixture. The systemctl adapter performs a real Apache graceful reload; use a VM for full systemd/provisioning tests.

Ports bind only to loopback. Credentials are public fixtures. No Docker socket or production files are mounted into app containers. SQL data persists in a named volume; container-local Apache files are restored from saved settings after recreation.

Sources and fixture scripts are mounted read-only and copied at startup. After source changes:

```sh
docker compose up -d --no-build --force-recreate server1 server2
```

Rebuild when changing the Dockerfile. Stop while preserving SQL data with `docker compose stop`. Reset this disposable lab with:

```sh
docker compose down -v
docker compose up -d --build
```

Static URL exceptions apply from phase 2; global phase 1 rules still run. The UI states this limitation.
