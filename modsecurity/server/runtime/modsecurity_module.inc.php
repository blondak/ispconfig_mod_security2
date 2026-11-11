<?php
require_once dirname(dirname(__DIR__)).'/lib/Compat.php';
require_once dirname(dirname(__DIR__)).'/lib/I18n.php';
require_once dirname(dirname(__DIR__)).'/lib/Config.php';
require_once dirname(dirname(__DIR__)).'/lib/Apply.php';
require_once dirname(dirname(__DIR__)).'/lib/Diagnostics.php';
require_once dirname(dirname(__DIR__)).'/lib/ServerConfig.php';
class modsecurity_module {
    public $module_name='modsecurity_module';
    public function onLoad() {
        global $app;
        $app->modules->registerTableHook('modsecurity_server_config',$this->module_name,'server');
        $app->modules->registerTableHook('modsecurity_config',$this->module_name,'process');
        $app->modules->registerTableHook('modsecurity_diagnostic_request',$this->module_name,'diagnostic');
    }
    public function process($table,$action,$data) {
        global $app,$conf;
        $r=$data['new']?:$data['old']; $id=(int)$r['domain_id']; $sid=(int)$conf['server_id'];
        if((int)$r['server_id']!==$sid) return;
        $revision=(int)$r['revision']; $state='error'; $message='';
        try {
            $web=$app->db->queryOneRecord('SELECT * FROM web_domain WHERE domain_id = ? AND server_id = ?', $id,$sid);
            if(!$web) throw new RuntimeException(\IspModsecurity\I18n::t('wrong_node'));
            $modules=[];$moduleCode=0;exec('/usr/sbin/apache2ctl -M 2>&1',$modules,$moduleCode);
            if($moduleCode!==0 || !\IspModsecurity\Compat::contains(implode('\n',$modules),'security2_module')) throw new RuntimeException(\IspModsecurity\I18n::t('module_inactive'));
            $path='/etc/apache2/ispconfig-modsecurity/web'.$id.'.conf';
            // Check that resync has installed the hook in the actual vhost, not merely in the template.
            $found=false;
            foreach(glob('/etc/apache2/sites-available/*.vhost') as $file) if(\IspModsecurity\Compat::contains(file_get_contents($file),'IncludeOptional '.$path)) { $found=true; break; }
            if(!$found) throw new RuntimeException(\IspModsecurity\I18n::t('resync_required'));
            $config=$action==='d'?\IspModsecurity\Config::defaults():json_decode($r['config_json'],true,512,JSON_THROW_ON_ERROR);
            $server=\IspModsecurity\ServerConfig::defaults();
            // Use applied server policy until a pending server change has passed configtest.
            if(is_file('/etc/apache2/ispconfig-modsecurity/server-policy.json')) $server=json_decode(file_get_contents('/etc/apache2/ispconfig-modsecurity/server-policy.json'),true,512,JSON_THROW_ON_ERROR);
            $text=\IspModsecurity\Config::render(\IspModsecurity\ServerConfig::effective($config,$server));
            $command=function($cmd) { $output=[]; $code=0; exec($cmd.' 2>&1',$output,$code); return $code===0; };
            \IspModsecurity\Apply::run($path,$text,fn()=>$command('/usr/sbin/apache2ctl configtest'),fn()=>$command('/usr/bin/systemctl reload apache2'));
            $state='applied';
        } catch(Throwable $e) { $message=$e->getMessage(); $app->log('ModSecurity web'.$id.': '.$message,LOGLEVEL_ERROR); }
        $app->dbmaster->query('INSERT INTO modsecurity_status (domain_id,server_id,revision,state,message,updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE revision=VALUES(revision),state=VALUES(state),message=VALUES(message),updated_at=NOW()', $id,$sid,$revision,$state,$message);
        if($state==='applied') $app->dbmaster->query('UPDATE modsecurity_status SET applied_revision = ?, applied_json = ? WHERE domain_id = ? AND server_id = ?', $revision,json_encode($config),$id,$sid);
    }
    public function server($table,$action,$data) {
        global $app,$conf;
        $r=$data['new']?:$data['old'];$sid=(int)$conf['server_id'];
        if((int)$r['server_id']!==$sid) return;
        $revision=(int)$r['revision'];$state='error';$message='';
        try {
            $config=$action==='d'?\IspModsecurity\ServerConfig::defaults():\IspModsecurity\ServerConfig::validate(json_decode($r['config_json'],true,512,JSON_THROW_ON_ERROR));
            $modules=[];$code=0;exec('/usr/sbin/apache2ctl -M 2>&1',$modules,$code);
            if($code!==0 || !\IspModsecurity\Compat::contains(implode('\n',$modules),'security2_module')) throw new RuntimeException(\IspModsecurity\I18n::t('module_inactive'));
            $dir='/etc/apache2/ispconfig-modsecurity/';
            $files=[$dir.'server.conf'=>\IspModsecurity\Config::render($config),$dir.'server-policy.json'=>json_encode($config,JSON_THROW_ON_ERROR)];
            $webs=$app->db->queryAllRecords("SELECT * FROM web_domain WHERE server_id=? AND type IN ('vhost','vhostsubdomain','vhostalias')",$sid);
            foreach($webs as $web) {
                $id=(int)$web['domain_id'];$path=$dir.'web'.$id.'.conf';
                if(\IspModsecurity\ServerConfig::locksMode($config)) {
                    $found=false;
                    foreach(glob('/etc/apache2/sites-available/*.vhost') as $vhost) if(\IspModsecurity\Compat::contains(file_get_contents($vhost),'IncludeOptional '.$path)) { $found=true;break; }
                    if(!$found) throw new RuntimeException(\IspModsecurity\I18n::t('resync_required'));
                }
                $row=$app->db->queryOneRecord('SELECT config_json FROM modsecurity_config WHERE domain_id=? AND server_id=?',$id,$sid);
                $site=$row?json_decode($row['config_json'],true,512,JSON_THROW_ON_ERROR):\IspModsecurity\Config::defaults();
                $files[$path]=\IspModsecurity\Config::render(\IspModsecurity\ServerConfig::effective($site,$config));
            }
            $command=function($cmd) { $out=[];$code=0;exec($cmd.' 2>&1',$out,$code);return $code===0; };
            \IspModsecurity\Apply::many($files,fn()=>$command('/usr/sbin/apache2ctl configtest'),fn()=>$command('/usr/bin/systemctl reload apache2'));
            $state='applied';
        } catch(Throwable $e) { $message=$e->getMessage();$app->log('ModSecurity server'.$sid.': '.$message,LOGLEVEL_ERROR); }
        $app->dbmaster->query('INSERT INTO modsecurity_server_status (server_id,revision,state,message,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE revision=VALUES(revision),state=VALUES(state),message=VALUES(message),updated_at=NOW()',$sid,$revision,$state,$message);
        if($state==='applied') $app->dbmaster->query('UPDATE modsecurity_server_status SET applied_revision=?,applied_json=? WHERE server_id=?',$revision,json_encode($config),$sid);
        if($app->dbmaster->errorNumber) throw new RuntimeException(\IspModsecurity\I18n::t('settings_write'));
    }
    public function diagnostic($table,$action,$data) {
        global $app,$conf;
        if($action==='d') return;
        $r=$data['new']; $id=(int)$r['domain_id']; $sid=(int)$conf['server_id'];
        if((int)$r['server_id']!==$sid) return;
        $state='ready'; $message=''; $result=[];
        try {
            $web=$app->db->queryOneRecord("SELECT * FROM web_domain WHERE domain_id = ? AND server_id = ? AND type IN ('vhost','vhostsubdomain','vhostalias')",$id,$sid);
            if(!$web) throw new RuntimeException(\IspModsecurity\I18n::t('wrong_node'));
            $result=\IspModsecurity\Diagnostics::read(\IspModsecurity\Diagnostics::logPath($web['domain']));
        } catch(Throwable $e) { $state='error'; $message=$e->getMessage(); }
        // Invalid UTF-8 in attacker-controlled log fields must not break the server queue.
        $json=json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        $app->dbmaster->query('INSERT INTO modsecurity_diagnostic_result (domain_id,server_id,revision,state,message,result_json,collected_at) VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE revision=VALUES(revision),state=VALUES(state),message=VALUES(message),result_json=VALUES(result_json),collected_at=NOW()', $id,$sid,(int)$r['revision'],$state,$message,$json);
        if($app->dbmaster->errorNumber) throw new RuntimeException(\IspModsecurity\I18n::t('diagnostics_write'));
    }

}
