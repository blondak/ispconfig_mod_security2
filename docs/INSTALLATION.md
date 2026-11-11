# Online installation and updates

The online installer downloads the extension from a published stable GitHub Release, verifies SHA-256 checksums and installs or updates the local ISPConfig node. It uses the same deployment backend as GitHub Actions, including a code backup and recovery on failure.

The release must include `install.sh`, `install.sh.sha256`, `deploy-remote.sh`, `deploy-remote.sh.sha256` and the versioned extension archive/checksum. The release workflow creates all of these assets. Until the first release is published, these download URLs are not available.

## Requirements

- Debian 12 and an existing ISPConfig installation at `/usr/local/ispconfig`, version 3.3.3p2 or newer. Tested with 3.3.3p2.
- Apache with the `security2` module enabled and the intended ruleset already configured.
- Bash, curl, CA certificates, GNU tar, sha256sum, flock and PHP CLI with mysqli, mbstring and posix.
- Root access for installation; `--check` does not require root.

The installer installs this extension. It does not install ISPConfig, Apache or ModSecurity packages. Existing ISPConfig configuration supplies database connections and determines the master/node SQL scope; database credentials are not passed on the command line.

## One-line installation

Run the installer directly from the latest stable release:

```sh
curl -fsSL --proto '=https' --proto-redir '=https' https://github.com/blondak/ispconfig_mod_security2/releases/latest/download/install.sh | sudo bash
```

To select a version, pass arguments after `bash -s --`:

```sh
curl -fsSL --proto '=https' --proto-redir '=https' https://github.com/blondak/ispconfig_mod_security2/releases/latest/download/install.sh | sudo bash -s -- --version 0.1.0
```

The pipe `|` passes the downloaded script to Bash. The installer verifies the extension archive and deployment backend checksums before installation. To also verify the bootstrap script itself before execution, use the download-and-run procedure below.

## Download and run

Download the installer and its checksum from the latest stable release, then verify it before executing:

```sh
release_url=https://github.com/blondak/ispconfig_mod_security2/releases/latest/download
curl --fail --location --proto '=https' --proto-redir '=https' "$release_url/install.sh" -o install.sh
curl --fail --location --proto '=https' --proto-redir '=https' "$release_url/install.sh.sha256" -o install.sh.sha256
sha256sum --check install.sh.sha256 && sudo bash install.sh
```

By default, it installs the latest stable release. Drafts and pre-releases are rejected. Downloads and redirects use HTTPS with certificate verification.

For a multiserver installation, select one explicit version and run it **on the master first, then on every target web node**:

```sh
sudo bash install.sh --version 0.1.0
```

`v0.1.0` is accepted too. To obtain the bootstrap script from the same pinned release, use `https://github.com/blondak/ispconfig_mod_security2/releases/download/v0.1.0` as `release_url` above.

To verify release downloads and checksums without installing anything:

```sh
bash install.sh --version 0.1.0 --check
```

This check downloads temporary files and verifies the archive/backend checksums and backend shell syntax. It does not perform the root-only target prerequisite checks or apply configuration.

## What happens during installation

1. Check target prerequisites and resolve the requested published release.
2. Download the extension archive and deployment backend, verifying each checksum against its exact expected filename.
3. Validate archive paths/types and the packaged version, lint PHP files and check the current Apache configuration.
4. Back up existing extension code, preserve its `dbversion` marker and run the local install/update using ISPConfig's extension installer.
5. Check Apache configuration again. If installation fails after replacing code, attempt to restore the previous code and extension files.

Repeated installation of the same version is supported. An older version than the installed one is rejected. Existing website settings, client capabilities and server defaults remain in the database. Updates can apply migrations shipped by the release; database changes are not automatically reversed during code recovery.

After the **first installation**, run **Tools → Resync → Websites** in ISPConfig for the target nodes, then sign in again to load the new tabs. Save settings and verify their Applied status after the ISPConfig server queue runs.

The installer does not create a Git tag, publish a release or configure automatic deployments. For GitHub deployment configuration, see [DEPLOYMENT.md](DEPLOYMENT.md). For manual installation, see the [project README](../README.md#installation).

## Repairing an early 0.1.0 installation

The refreshed 0.1.0 package fixes HTTP 500 on the first login and on the server settings page when the panel cannot read the extension source directory. Panel libraries and translations are copied into `/usr/local/ispconfig/interface/lib/modsecurity`; widening `open_basedir` or changing extension-directory permissions is unnecessary.

Run the verified installer again on the panel/master and web nodes using `--version 0.1.0`. Reinstallation keeps database settings. Sign out and sign in again after updating so the panel registers all plugins. If the panel's PHP OPcache has timestamp validation disabled, reload the PHP service serving ISPConfig to load the updated code.
