<?php
if(PHP_VERSION_ID<70400) { fwrite(STDERR,"ModSecurity requires PHP 7.4 or newer.\n");exit(1); }
if(PHP_SAPI!=='cli' || posix_geteuid()!==0) exit("Run as root using CLI.\n");
chdir('/usr/local/ispconfig/server');
require 'lib/config.inc.php';
require 'lib/app.inc.php';
$app->uses('extension_installer,extension_installer_base');
$mode=$argv[1]??'--install';
try {
    if($mode==='--uninstall') {
        $ok=$app->extension_installer->uninstall_extension('modsecurity');
    } elseif($mode==='--enable-files' || $mode==='--disable-files') {
        // Recovery helpers used by the release deployer; no configuration or schema reset.
        $method=$mode==='--enable-files'?'enable_files':'disable_files';
        $ok=$app->extension_installer->$method('modsecurity');
    } elseif($mode==='--update-local') {
        require_once __DIR__.'/installer.php';
        $installer=new modsecurity_installer();
        $ok=$installer->update()
            && $app->extension_installer->run_sql_migrations('modsecurity')
            && $app->extension_installer->register_master_grants('modsecurity')
            && $installer->enable();
    } elseif($mode==='--install') {
        $ok=$app->extension_installer->install_extension('modsecurity',null,false);
    } else {
        throw new RuntimeException('Use --install, --update-local, --uninstall, --enable-files or --disable-files.');
    }
    if(!$ok) throw new RuntimeException(implode("\n",$app->extension_installer->getErrors()));
} catch(Throwable $e) {
    fwrite(STDERR,"Operation failed: ".$e->getMessage()."\n"); exit(1);
}
echo "Operation completed. After initial installation, Resync websites. Sign in to the panel again.\n";
