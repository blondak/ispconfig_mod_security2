# GitHub Releases and automatic deployment

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
