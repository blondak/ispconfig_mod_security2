# Runtime compatibility and installation layout

## Supported versions

| Component | Requirement / verification |
|---|---|
| ISPConfig | Minimum 3.3.3p2; integration tested with the supplied 3.3.3p2 sources |
| Operating system / web stack | Online installer and full lab target Debian 12, Apache 2.4 and ModSecurity 2 with OWASP CRS 3.3.x |
| PHP runtime | Minimum 7.4; syntax and functional CI on 7.4 and 8.2 |
| Recommended PHP on Debian 12 | 8.2, including the complete panel / two-node / Apache test suite |
| Older PHP | Extension disabled; guarded entry points regression-tested on PHP 7.0, which is not a supported extension runtime |

## PHP contract

The extension runtime supports PHP **7.4 and newer**. PHP **8.2** is the recommended and full integration-test target on Debian 12. PHP compatibility is tested separately from the operating system requirements of the online installer, which still targets Debian 12.

The supplied ISPConfig 3.3.3p2 archive contains two different boundaries: its installer checks for PHP >= 7.0, while its README describes panel support as PHP 7.4–8.4 and recommends the operating system's default PHP. The extension follows the documented panel minimum of PHP 7.4, rather than the installer's lower acceptance check.

Production files use PHP 7.4 syntax. String helpers avoid depending on PHP 8 functions. JSON exceptions and invalid UTF-8 substitution use the native PHP 7.4 facilities. No global PHP functions are redefined.

CI lints every production PHP file and runs functional checks under PHP 7.4 and 8.2. The complete panel/Apache/multiserver suite runs under PHP 8.2. A successful CLI check is not proof of the PHP version used by the web panel.

For the standard Apache/mod_fcgid panel wrapper, the online installer reads the literal interpreter path and queries its version as the unprivileged `ispconfig` user. It does not execute the wrapper as root. An older panel interpreter produces a warning and installation can continue, leaving panel integration inactive. The installer itself requires CLI PHP >= 7.4. For other or custom handlers it explicitly reports that the panel runtime was not detected; inspect the active FPM pool or Apache handler separately.

Tiny entry points check PHP before including the actual runtime. On PHP below 7.4, no extension events or menus are registered, and cached plugin callbacks are harmless. A warning containing the actual PHP version is written once per request to the panel/worker error log. Direct settings and diagnostics URLs return HTTP 503 with an explanation in EN/DE/CZ. No configuration or database settings are changed by the disabled entry points. This safeguard cannot make ISPConfig itself compatible with an unsupported PHP interpreter.

The installed panel entry points load their implementation from `interface/lib/modsecurity/runtime/`; worker bootstraps load the root-owned `extensions/modsecurity/server/runtime/`. Keeping newer syntax in separate files is necessary: PHP parses a whole file before executing an early version check.

## Installed paths

| Path | Purpose | Ownership/access |
|---|---|---|
| `/usr/local/ispconfig/extensions/modsecurity/` | Canonical package source, root installer, worker code and `dbversion` | Root-owned; new releases use `root:ispconfig`, directories 0750 and ordinary files 0640 |
| `/usr/local/ispconfig/interface/lib/modsecurity/` | Installed panel library/translation copies | Native extension installer copies, owned by `ispconfig`; normal interface file permissions |
| `/usr/local/ispconfig/interface/lib/plugins/modsecurity_*.inc.php` | Panel event hooks | Native installer copies |
| `/usr/local/ispconfig/interface/web/sites/modsecurity*` and `/admin/modsecurity*` | Controllers, templates and scoped styling | Native installer copies; core templates remain unchanged |
| `/usr/local/ispconfig/server/mods-enabled/` and `/plugins-enabled/` | Worker module/plugin entry points | Links to root-owned package code |
| `/etc/apache2/ispconfig-modsecurity/` | Generated Apache snippets and applied server policy | Root-owned, outside webroots; written by the worker |

The package layout and copies/links follow ISPConfig's extension `install/file.list` mechanism. There is one maintained source of each shared library in the package; the interface copies are installation artifacts, not a separate implementation. Explicit file copies refresh modification times so normal OPcache timestamp checks detect updates.

Panel hooks read their libraries through `ISPC_LIB_PATH`, inside the interface tree. Worker processes use the root-owned package libraries and never execute interface-owned copies. This keeps the panel compatible with a restricted `open_basedir` without widening it to all extensions. Existing parent-directory ownership or access policy is preserved when the deployment base already exists.

The reported production parse error at an arrow function was caused by the panel's PHP version. Separately, tests reproduced filesystem-access failures when panel hooks included package libraries outside `open_basedir`. The runtime compatibility changes and installed interface copies address different conditions; one does not substitute for the other.
