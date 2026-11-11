# Development and tests

```sh
./scripts/check.sh

docker build -f tests/apache/Dockerfile -t ispconfig-modsecurity-test .
docker run --rm ispconfig-modsecurity-test

cd tests/setup
docker compose up -d --build
docker compose exec -T server1 php /lab/smoke.php
docker compose exec -T server1 php /lab/server_settings.php
docker compose exec -T server1 php /lab/stale_session.php
```

The disposable [two-node lab](../tests/setup/README.md) provides an administrator, two clients, two websites and actual queue replication. It runs the components needed by this extension; full mail/DNS/FTP provisioning and systemd require separate VM testing. See [TEST_RESULTS.md](../TEST_RESULTS.md) for completed checks and remaining coverage.

The local checks include online-installer tests using fixture GitHub responses, without network access. CI also exercises the installer inside the disposable lab node, including downgrade rejection and failed-install recovery.

Build a distributable archive with `./scripts/package-release.sh 0.1.0`. Build timestamps default to zero for reproducibility; `SOURCE_DATE_EPOCH` can set an explicit timestamp.

## Commit attribution

Include this trailer in commits made with OpenAI Codex assistance:

```text
Co-authored-by: Codex <codex@openai.com>
```
