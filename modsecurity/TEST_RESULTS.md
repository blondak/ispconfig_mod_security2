# Verification of 0.1.0 — 2026-11-11

## Local checks

`./scripts/check.sh` passed:

- 19 configuration, validation and apply/recovery assertions.
- 21 diagnostic parser, grouping, privacy and safe-path assertions.
- 4 diagnostic routing/error assertions; 4 server template-hook assertions.
- 3 explicit-primary-key / atomic-datalog regression assertions.
- 7 client/reseller permission-chain assertions.
- 4 server-policy and multi-file rollback assertions.
- 29 regex validation, serialization, Livewire matching and policy assertions.
- 139 messages present in English, German and Czech; locale selection, validation, worker-error translation and template coverage checks.
- Actual ISPConfig template engine rendered HTTP and HTTPS hooks.
- PHP/Bash syntax checks and reproducible release packaging succeeded.

All four GitHub YAML files parsed successfully. The pre-regex website/server/installer suite has also passed on GitHub Actions.

## Real ISPConfig two-node lab

The Docker lab uses Debian 12, ISPConfig 3.3.3p2 from the supplied archive, MariaDB and two independent Apache/ModSecurity nodes. It has one administrator and two clients with separate websites. The extension installer creates its initial SQL and database grants. Workers use actual ISPConfig datalog processing and replication.

`tests/setup/smoke.php`: **88 successful HTTP end-to-end assertions**, including:

- Actual login, anonymous/cross-client rejection, native website tabs and AJAX fragments.
- Core website tab transitions through real POST validation without changing website identity, document roots or system users; invalid input stays on the core form.
- Website limits/exclusions saved through the queue and Apache configtest/graceful reload on both nodes.
- Independent limit override checkboxes, stale numeric input ignored when unchecked, empty checked values rejected and limit relationships enforced.
- Root-owned error-log collection, summary/detection correlation, filters, correct node isolation and actual second-node database replication.
- Native client Limits permission saved both enabled and disabled; forged per-site permission flags rejected; revocation blocks an already-open form without logging out. Diagnostics remain readable.
- Actual settings, diagnostics, validation errors and client Limits rendered in EN, DE and CZ.

`tests/setup/server_settings.php`: **29 successful assertions**, including native Server Config tabs, validated core POST transitions and routing, admin-only access, independent settings for both servers, actual XSS blocking on one server and DetectionOnly on the other, server Off, website override precedence, forged site-mode protection, and EN/DE/CZ server pages. The test restores inherited server defaults.

`tests/setup/stale_session.php`: **8 successful regression assertions**. A historical extension callback in a newly created test session is repaired without a logout or HTTP 500. Other plugin hooks and website ACLs remain intact.

## Actual Apache / ModSecurity

`tests/apache/run.sh`: **33 successful HTTP assertions** using the available Debian lab image with current source files mounted read-only. Configtest passed.

Confirmed whole-site Off, DetectionOnly and rule removal for phases 1/2; exact/prefix URL exceptions and URL Off for phase 2; isolation of other virtualhosts. Global phase 1 rules still block URLs with static LocationMatch exceptions, as documented in the UI and README.

## Release deployment

Built the 0.1.0 archive and checksum and executed `scripts/deploy-remote.sh` inside the first disposable lab node. Package validation, PHP lint, schema-marker preservation, local update, database grants and Apache configtests succeeded; previous code was backed up.

A separate fixture package deliberately threw during installer enable. Deployment returned failure and restored the previous code and extension files. No production deployment was performed. The complete GitHub event/SSH transport path still requires a configured repository and deployment credentials.

## Online installer

`tests/installer.sh`: **14 fixture checks** passed, covering latest/pinned release resolution, stdin invocation with arguments, HTTPS-only requests, invalid arguments, tampered archive/backend checksums, unsafe checksum filenames, multiple entries, draft/pre-release rejection, version mismatch and unavailable releases. No real network requests are used.

`tests/setup/install_online.sh`: **3 checks in the disposable lab node** passed: installation through the online entry point with schema-marker preservation, downgrade rejection, and working-code recovery after a deliberately broken installer. GitHub responses and downloads are supplied by local fixtures; no public release was created or downloaded.

## Restricted panel filesystem regression

Reproduced the reported sequence with `open_basedir` excluding `/usr/local/ispconfig/extensions`: the first normal login returned HTTP 500, a subsequent panel request succeeded, and `/admin/modsecurity.php` returned HTTP 500. Apache logged failed includes of I18n.php and ServerConfig.php outside the allowed tree.

The installer now copies panel libraries and translations into `interface/lib/modsecurity`, using regular files rather than symlinks. UI controllers, menu providers and plugins load only those copies. Individual copy entries refresh file timestamps for PHP OPcache update detection; worker libraries remain in the root-owned extension directory.

`tests/setup/panel_runtime.php`: **23 assertions** passed with the restriction enabled, covering runtime isolation and normal first login/full panel/server/website rendering for Dashboard, Sites and System start modules. The existing **88 website**, **29 server** and **8 session** assertions also passed under the restricted panel configuration. The lab now enables this restriction by default so CI covers this deployment condition.

## URL regular expressions

Added a Regular expression scope with EN/DE/CZ labels. The user-supplied Livewire expression is preserved, validated and written to a quoted LocationMatch argument. Apache argument backslashes are escaped; control characters, whitespace, quotes, overlong input and invalid PCRE syntax are rejected.

`tests/regex.php`: **29 checks** passed. `tests/setup/regex_settings.php`: **7 HTTP assertions** passed for scope selection, client save/reload, multiple rule IDs, worker application and rejection without revision changes. The Apache suite now has **33 checks**, including valid/invalid Livewire hashes, path anchors, backslash semantics, URL disabling, phase 1 behavior and another vhost's isolation.

## Scope and remaining coverage

The lab provisions fixture vhosts instead of running the full hosting stack. Mail/DNS/FTP daemons and systemd are excluded; the systemctl adapter performs a real Apache graceful reload. Full operating-system provisioning/upgrades need a VM.

No production installation, visual browser inspection, real HTTPS traffic or large multipart upload was performed. HTTP tests validate generated pages, links, CSRF tokens and form results; HTTPS Include generation was checked with the real template engine. Existing ISPConfig templates are not modified. Browser-host visual inspection is unavailable in this environment.
