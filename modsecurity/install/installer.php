<?php
class modsecurity_installer extends extension_installer_base {
    public function install() {
        $list=__DIR__.'/file.list';
        if(!is_dir('/usr/local/ispconfig/interface/web/sites')) {
            $lines=file($list,FILE_IGNORE_NEW_LINES);
            $lines=array_filter($lines,fn($line)=>!str_contains($line,':interface/'));
            if(file_put_contents($list,implode("\n",$lines)."\n")===false) throw new RuntimeException('Cannot prepare the node-only installation.');
        }
        return true;
    }
    public function update() { return $this->install(); }
    public function enable() {
        global $app;
        if(is_dir('/etc/apache2/conf-enabled')) {
            $file='/etc/apache2/conf-enabled/ispconfig-modsecurity.conf';
            $include="# Managed by ISPConfig ModSecurity extension\nIncludeOptional /etc/apache2/ispconfig-modsecurity/server.conf\n";
            if(is_file($file) && file_get_contents($file)!==$include) throw new RuntimeException('An unmanaged Apache ModSecurity include already exists.');
            if(file_put_contents($file,$include)!==strlen($include) || !chmod($file,0644)) throw new RuntimeException('Cannot install the Apache server defaults include.');
        }
        if(is_dir('/usr/local/ispconfig/interface/web/admin/lib') && !is_dir('/usr/local/ispconfig/interface/web/admin/lib/menu.d')) {
            if(!mkdir('/usr/local/ispconfig/interface/web/admin/lib/menu.d',0755,true)) throw new RuntimeException('Cannot create the admin menu directory.');
        }
        if(!$app->extension_installer->enable_files('modsecurity')) throw new RuntimeException('Cannot enable extension files.');
        return true;
    }
    public function disable() {
        global $app;
        foreach(glob('/etc/apache2/ispconfig-modsecurity/web*.conf') as $file) {
            if(preg_match('/^\s*Sec/m',file_get_contents($file))) throw new RuntimeException('Reset all websites to Inherit with no custom limits or exclusions and wait for successful application first.');
        }
        $global='/etc/apache2/ispconfig-modsecurity/server.conf';
        if(is_file($global) && preg_match('/^\s*Sec/m',file_get_contents($global))) throw new RuntimeException('Reset server defaults to Inherit and clear both limits before uninstalling.');
        $ok=$app->extension_installer->disable_files('modsecurity');
        if($ok && is_file('/etc/apache2/conf-enabled/ispconfig-modsecurity.conf')) unlink('/etc/apache2/conf-enabled/ispconfig-modsecurity.conf');
        return $ok;
    }
    public function uninstall() { return true; }

}
