# Development and tests

```sh
./scripts/check.sh

docker build -f tests/apache/Dockerfile -t ispconfig-modsecurity-test .
docker run --rm ispconfig-modsecurity-test

cd tests/setup
docker compose up -d --build
docker compose exec -T server1 php /lab/panel_runtime.php
docker compose exec -T server1 php /lab/smoke.php
docker compose exec -T server1 php /lab/regex_settings.php
docker compose exec -T server1 php /lab/server_settings.php
docker compose exec -T server1 php /lab/stale_session.php
```

The disposable [two-node lab](../tests/setup/README.md) provides an administrator, two clients, two websites and actual queue replication. It runs the components needed by this extension; full mail/DNS/FTP provisioning and systemd require separate VM testing. See [TEST_RESULTS.md](../TEST_RESULTS.md) for completed checks and remaining coverage.

The local checks include online-installer tests using fixture GitHub responses, without network access. CI also exercises the installer inside the disposable lab node, including downgrade rejection and failed-install recovery.

Build a distributable archive with `./scripts/package-release.sh 0.1.0`. Build timestamps default to zero for reproducibility; `SOURCE_DATE_EPOCH` can set an explicit timestamp.

## PHP compatibility checks

Run `scripts/check-compat.sh` with the interpreter under test. CI runs the production syntax and functional suite in official PHP 7.4.33 and 8.2 CLI images, with container networking disabled and the repository mounted read-only. The complete hosting-component lab remains on Debian 12 / PHP 8.2.

## Commit attribution

Include this trailer in commits made with OpenAI Codex assistance:

```text
Co-authored-by: Codex <codex@openai.com>
```

CI also runs `tests/unsupported_runtime.php` on actual PHP 7.0 to verify that entry points never parse the supported runtime, cached callbacks remain harmless, warnings are localized and emitted once per request, and native installation rejects unsupported CLI PHP. This is disabled-runtime coverage, not PHP 7.0 support.
