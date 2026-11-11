# ISPConfig ModSecurity extension

[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-support-yellow)](https://buymeacoffee.com/blondak)

ModSecurity controls and diagnostics integrated into **ISPConfig 3.3.3p2**, targeting **Debian 12, Apache, ModSecurity 2 and OWASP CRS 3.3.4**, including multiserver installations. Version **0.1.0** is the initial release.

The extension adds native tabs without modifying ISPConfig core templates. Its UI follows the ISPConfig account language: **English, German and Czech** (`en`, `de`, `cz`; `cs` is also accepted). Other languages fall back to English. Rule messages from logs remain in their original language.

## Website controls

Open **Sites → Websites → website → ModSecurity**. The Settings and Diagnostics subtabs share the normal ISPConfig content area.

- Inherit, On, Detection only and Off modes.
- `SecRuleRemoveById` for a whole website, an exact URL path or a path prefix ending in `/`. Enter multiple IDs separated by spaces or commas.
- Disable protection for a URL, subject to administrator permission.
- Request-body limits including/excluding files, in MiB. Each **Override limit** checkbox enables a custom value. Unchecked means inheritance. PHP settings are unchanged.
- Desired and applied revisions, application status and errors. A saved change reaches Apache through the ISPConfig server queue, after a successful configuration test and reload.

**URL exceptions apply from phase 2. Global phase 1 rules run earlier.** Whole-site mode and rule exclusions also cover phase 1. Exceptions are additive: a narrow exception cannot restore a rule removed by a broader one.

Paths accept a restricted ASCII subset starting with `/`, without query strings, percent encoding, whitespace or `..`. The generator creates anchored regular expressions. Method/parameter exclusions and importing arbitrary Apache directives are outside this version's scope.

## Client permissions

An administrator enables **Clients → client → Limits → ModSecurity → Allow changes to ModSecurity settings**. The same capability is available in reseller limits and client templates. It defaults to **disabled**. A reseller's permission also caps its child clients.

Without this capability, website settings are read-only and diagnostics remain available. Revocation takes effect on the next request, including submissions from already open forms; existing website configuration is retained. The capability supplements normal website ACLs and never grants access to another client's website.

Per-website administrator policy controls whether clients may disable protection/use Detection only and their maximum body sizes. Administrators can edit websites regardless of the client capability. Absolute body-size maximum: 1024 MiB.

## Server defaults

Open **System → Server Config → server → ModSecurity**, or **System → ModSecurity** to select a web server. Only administrators can access this page.

Each server has its own mode and both default body limits. **Inherit** preserves the existing Apache configuration. For example, one node can block in On mode while another uses Detection only.

**Allow websites to override the mode and disable protection for URLs** determines precedence. When unchecked and the server mode is explicit, that mode is enforced in the extension's managed website snippets. Previously saved website modes and URL disabling remain in the database but do not take effect. Rule-ID exclusions remain effective. With overrides allowed, website-specific settings take precedence over server defaults.

Server defaults are loaded in Apache's main configuration, so other virtualhosts on that Apache instance also inherit them unless they have explicit overrides. Enforcing the server mode applies to ISPConfig websites with the extension Include installed. Existing administrator-supplied Apache directives are not imported or rewritten. Resync websites before enforcing a server mode.

A server change regenerates its affected snippets and performs one configtest/reload. Failure restores the previous files together. Server application status is tracked separately from website revisions.

## Installation

1. Use ISPConfig **3.3.3p2** on the master and target web nodes. Back up the databases and Apache configuration.
2. Extract `modsecurity-0.1.0.tar.gz` into `/usr/local/ispconfig/extensions/` on the master and all target web nodes. The resulting directory must be `/usr/local/ispconfig/extensions/modsecurity/`.
3. Run as root, **master first, then web nodes**:

   ```sh
   php /usr/local/ispconfig/extensions/modsecurity/install/local.php --install
   ```

   This uses ISPConfig's extension installer, initial node/master SQL and declared database grants. It does not download the extension from the ISPConfig catalog. Keep extension code owned by root and readable by the panel.
4. Run **Tools → Resync → Websites** for the target nodes to add the extension Include to HTTP/HTTPS virtualhosts. Sign in to the panel again to load new plugins.
5. Enable the appropriate client capability and configure administrator policies. Test one website before expanding usage.
6. Save and wait for the server queue, then refresh and verify **Applied**. After correcting an application error, save again to retry.

Apache nodes need `/usr/sbin/apache2ctl` and `/usr/bin/systemctl reload apache2`. Managed snippets live in `/etc/apache2/ispconfig-modsecurity/`, outside webroots. The installer creates its own `/etc/apache2/conf-enabled/ispconfig-modsecurity.conf` include; core ISPConfig files remain unchanged.

Manual ModSecurity directives remain in place. Remove duplicates after transferring their settings to the extension. Website moves require Resync and a new save on the destination; automatic migration and mirror topologies are not implemented.

There are no incremental migrations for the initial 0.1.0 release. Its initial schema contains all current tables and capability columns. Later releases can use ISPConfig's numbered migration mechanism through `--update-local`; production updates must ship appropriate migrations.

## Diagnostics

Open the website's **ModSecurity → Diagnostics** tab and click **Fetch current records from the web node**. After queue processing, refresh the view. The read-only diagnostic page is also available from **Sites → ModSecurity → ModSecurity diagnostics**.

The worker derives `/var/log/ispconfig/httpd/<domain>/error.log` from the local website record. ISPConfig bind mounts such as `/var/www/<domain>/log/error.log` point to the same file. Users cannot supply a filesystem path. The canonical file must be a root-owned regular file; symlinks are rejected.

The snapshot contains up to 100 recent requests from the final 2 MiB of the current file, grouped by `unique_id`. Filters match rule ID, path and blocked requests. A website can request collection once every 30 seconds. Rotated files are excluded and related lines may be missing. Only the latest snapshot is retained.

Rule families 949xxx, 959xxx and 980xxx are labeled as evaluation/summary. A transaction containing only 949110 and 980130 cannot identify the original 941xxx XSS detection rule. The page explains this instead of suggesting an exception for the blocking threshold. Diagnostics never create exclusions automatically.

Raw lines, client IPs, referers and request bodies are not copied into the snapshot; URI query strings are removed. Rule messages can still include values logged by the rule itself. Website read ACLs apply and displayed text is HTML escaped.

## GitHub Releases and automatic deployment

All automation uses **GitHub Actions**:

1. Push a version tag matching `modsecurity/VERSION`, e.g. `v0.1.0`.
2. The release workflow runs unit checks, Apache/CRS tests and the two-node ISPConfig lab. It packages the extension and creates a **draft GitHub Release** with the archive and SHA-256 checksum.
3. Review the draft and **publish it in GitHub**. Publishing a stable release automatically deploys its attached package when deployment is configured. Pre-releases are not deployed.

Assets are attached before publication. The deployment uses the Release assets, checks their checksum, then updates the master followed by web nodes. GitHub documents the [`release: published` event](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#release) and [token-triggered workflow restrictions](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/trigger-a-workflow); publish the generated draft through GitHub so the deployment event is emitted.

In the repository, set the Actions **repository variable** `DEPLOY_ENABLED=true`. Create the `production` environment and configure these environment variables/secrets:

| Name | Type | Value |
|---|---|---|
| `DEPLOY_HOSTS` | Variable | One SSH DNS name or IPv4 address per line, master first |
| `DEPLOY_USER` | Variable | SSH user with root access; default `root` |
| `DEPLOY_PORT` | Variable | SSH port; default `22` |
| `DEPLOY_SSH_KEY` | Secret | Private deployment key |
| `DEPLOY_KNOWN_HOSTS` | Secret | Verified OpenSSH known_hosts entries, including port notation when needed |

Targets need Bash, PHP CLI with extension dependencies, GNU tar, sha256sum, flock and Apache. The SSH session must run as root; sudo escalation is not performed. Strict host-key checking is enabled. Production must be reachable from the Actions runner; use an appropriately connected runner for private hosts.

The deployer stages and lints the package, preserves `dbversion`, backs up previous code, runs the local installer/update and checks Apache configuration. On failure it attempts to restore previous code. Database migrations are not reversed automatically. Deployment stops at the first failed node; earlier nodes retain the deployed release. Initial installation still requires website Resync in ISPConfig.

The **Deploy published release** workflow also accepts an existing published tag through **Run workflow** for retries. No remote credentials are stored in this repository. The workflows have been prepared and locally checked; running them on GitHub requires pushing the repository and configuring deployment.

## Development and tests

```sh
./scripts/check.sh

docker build -f tests/apache/Dockerfile -t ispconfig-modsecurity-test .
docker run --rm ispconfig-modsecurity-test

cd tests/setup
docker compose up -d --build
docker compose run --rm --no-deps --entrypoint php server1 /lab/smoke.php
docker compose run --rm --no-deps --entrypoint php server1 /lab/server_settings.php
docker compose run --rm --no-deps --entrypoint php server1 /lab/stale_session.php
```

The disposable [two-node lab](tests/setup/README.md) provides an administrator, two clients, two websites and actual queue replication. It runs the components needed by this extension; full mail/DNS/FTP provisioning and systemd require separate VM testing. See [TEST_RESULTS.md](TEST_RESULTS.md) for completed checks and remaining coverage.

Build a distributable archive with `./scripts/package-release.sh 0.1.0`. Build timestamps default to zero for reproducibility; `SOURCE_DATE_EPOCH` can set an explicit timestamp.

## Uninstall

Reset website modes to **Inherit**, clear both limit overrides and remove exclusions. Reset server defaults to **Inherit** and clear their limit overrides. Wait for successful application on all nodes. Uninstall refuses while active extension directives remain.

Run on web nodes first and master last:

```sh
php /usr/local/ispconfig/extensions/modsecurity/install/local.php --uninstall
```

Audit tables and empty snippets remain. After Resync without the extension hook, an administrator can remove remaining empty files and, after backup, database tables/capability columns if no longer needed.

## License and credits

Original extension code and its tests are licensed under [MIT](LICENSE). The bundled ISPConfig source archive and other dependencies retain their own licenses.

Maintained by [blondak](https://github.com/blondak), with implementation assistance from OpenAI Codex. Commits made with that assistance use:

```text
Co-authored-by: Codex <codex@openai.com>
```

If this extension helps you, [support development on Buy Me a Coffee](https://buymeacoffee.com/blondak).
