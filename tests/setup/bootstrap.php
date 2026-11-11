<?php
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$server=(int)getenv('LAB_SERVER_ID');
$db=new mysqli('db','root','lab-db-root');
$db->set_charset('utf8mb4');
function sqlfile(mysqli $db,string $path): void {
    $db->multi_query(file_get_contents($path));
    do { if($r=$db->store_result()) $r->free(); } while($db->more_results() && $db->next_result());
}
function ins(mysqli $db,string $table,array $values): void {
    $keys=implode(',',array_map(fn($v)=>'`'.$v.'`',array_keys($values)));
    $vals=implode(',',array_map(fn($v)=>"'".$db->real_escape_string((string)$v)."'",array_values($values)));
    $db->query("INSERT INTO `$table` ($keys) VALUES ($vals)");
}
if($server===1) {
    $exists=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='ispc_master' AND TABLE_NAME='lab_seed_complete'")->num_rows;
    if(!$exists) {
        foreach(['ispc_master','ispc_node2'] as $schema) {
            $db->query("DROP DATABASE IF EXISTS `$schema`");
            $db->query("CREATE DATABASE `$schema` CHARACTER SET utf8mb4");$db->select_db($schema);
            $db->query("SET SESSION sql_mode=''");
            sqlfile($db,'/opt/ispc-source/install/sql/ispconfig3.sql');
        }
        $db->query("CREATE USER IF NOT EXISTS 'ispc_master'@'%' IDENTIFIED BY 'lab-master-db'");
        $db->query("GRANT ALL ON ispc_master.* TO 'ispc_master'@'%'");
        $db->query("CREATE USER IF NOT EXISTS 'ispcsrv2'@'%' IDENTIFIED BY 'lab-node2-db'");
        $db->query("GRANT ALL ON ispc_node2.* TO 'ispcsrv2'@'%'");
        $db->query("GRANT SELECT ON ispc_master.sys_datalog TO 'ispcsrv2'@'%'");
        $db->query("GRANT SELECT,UPDATE ON ispc_master.server TO 'ispcsrv2'@'%'");
        $db->query("GRANT SELECT ON ispc_master.web_domain TO 'ispcsrv2'@'%'");
        $db->query("GRANT SELECT ON ispc_master.sys_ini TO 'ispcsrv2'@'%'");
        foreach(['ispc_master','ispc_node2'] as $schema) {
            $db->select_db($schema);
            $system=file_get_contents('/opt/ispc-source/install/tpl/system.ini.master');
            $system=str_replace('session_timeout=0','session_timeout=3600',$system);
            $q=$db->prepare('UPDATE sys_ini SET config=? WHERE sysini_id=1');$q->bind_param('s',$system);$q->execute();
            $ini=file_get_contents('/opt/ispc-source/install/tpl/server.ini.master');
            $ini=preg_replace('/\{[^}]+\}/','',$ini);
            foreach([1,2] as $sid) ins($db,'server',['server_id'=>$sid,'server_name'=>'server'.$sid.'.lab.test','web_server'=>1,'db_server'=>1,'dns_server'=>1,'config'=>$ini,'sys_perm_user'=>'riud','sys_perm_group'=>'riud']);
            $pass=password_hash('Admin-Lab-123!',PASSWORD_BCRYPT);
            $q=$db->prepare("UPDATE sys_user SET passwort=?, startmodule='sites', language='en' WHERE userid=1");$q->bind_param('s',$pass);$q->execute();
            foreach([1,2] as $client) {
                $uid=$client+1; $group=$client+1; $clientPassword=password_hash('Client'.$client.'-Lab-123!',PASSWORD_BCRYPT);
                ins($db,'client',['client_id'=>$client,'sys_userid'=>1,'sys_groupid'=>1,'sys_perm_user'=>'riud','sys_perm_group'=>'riud','company_name'=>'Lab Client '.$client,'contact_name'=>'Client '.$client,'username'=>'client'.$client,'password'=>$clientPassword,'default_webserver'=>$client,'web_servers'=>(string)$client]);
                ins($db,'sys_group',['groupid'=>$group,'name'=>'client'.$client,'description'=>'Lab client '.$client,'client_id'=>$client]);
                ins($db,'sys_user',['userid'=>$uid,'username'=>'client'.$client,'passwort'=>$clientPassword,'modules'=>'sites,tools,help','startmodule'=>'sites','typ'=>'user','groups'=>(string)$group,'default_group'=>$group,'client_id'=>$client,'language'=>'en']);
                if($schema==='ispc_node2' && $client!==2) continue;
                ins($db,'web_domain',['domain_id'=>$client,'server_id'=>$client,'sys_userid'=>$uid,'sys_groupid'=>$group,'sys_perm_user'=>'riud','sys_perm_group'=>'ru','sys_perm_other'=>'','domain'=>'web'.$client.'.lab.test','type'=>'vhost','vhost_type'=>'name','ip_address'=>'*','document_root'=>'/var/www/clients/client'.$client.'/web'.$client,'system_user'=>'web'.$client,'system_group'=>'client'.$client,'hd_quota'=>1024,'traffic_quota'=>-1,'php'=>'no','cgi'=>'n','suexec'=>'n','subdomain'=>'none','active'=>'y']);
            }
            ins($db,'server_ip',['server_id'=>1,'ip_address'=>'*','virtualhost'=>'y']);
            ins($db,'server_ip',['server_id'=>2,'ip_address'=>'*','virtualhost'=>'y']);
        }
        $db->query('CREATE TABLE ispc_master.lab_seed_complete (ready int NOT NULL)');
    }
} else {
    $db->select_db('ispc_node2');
}
if($server===1) {
    // Metadata only: lets the native client Limits form validate its DNS selects.
    $db->query("UPDATE ispc_master.server SET dns_server=1 WHERE server_id IN (1,2)");
    $db->query("GRANT SELECT,INSERT,UPDATE ON ispc_master.sys_log TO 'ispcsrv2'@'%'");
    $db->query("UPDATE ispc_master.client c JOIN ispc_master.sys_user u ON u.client_id=c.client_id SET c.username=u.username, c.password=u.passwort WHERE c.client_id IN (1,2)");
}
$local=$server===1?'ispc_master':'ispc_node2';$user=$server===1?'ispc_master':'ispcsrv2';$pass=$server===1?'lab-master-db':'lab-node2-db';
$template=file_get_contents('/opt/ispc-source/install/tpl/config.inc.php.master');
$replace=['mysql_server_host'=>'db','mysql_server_port'=>'3306','mysql_server_database'=>$local,'mysql_server_ispconfig_user'=>$user,'mysql_server_ispconfig_password'=>$pass,'mysql_server_client_flags'=>'0','mysql_master_server_host'=>'db','mysql_master_server_port'=>'3306','mysql_master_server_database'=>'ispc_master','mysql_master_server_ispconfig_user'=>$user,'mysql_master_server_ispconfig_password'=>$pass,'mysql_master_server_client_flags'=>'0','server_id'=>(string)$server,'ispconfig_log_priority'=>'0','theme'=>'default','language'=>'en','language_file_import_enabled'=>'false','timezone'=>'Europe/Prague'];
foreach($replace as $key=>$value) $template=str_replace('{'.$key.'}',$value,$template);
if(preg_match('/\{[a-z_]+\}/',$template,$m)) throw new RuntimeException('Unfilled config: '.$m[0]);
foreach(['interface','server'] as $part) file_put_contents('/usr/local/ispconfig/'.$part.'/lib/config.inc.php',$template);
file_put_contents('/usr/local/ispconfig/server/lib/config.inc.local.php',"<?php\n\$conf['start_session']=false;\n");
file_put_contents('/usr/local/ispconfig/server/lib/mysql_clientdb.conf',"<?php\n\$clientdb_host='db';\n\$clientdb_port=3306;\n\$clientdb_user='root';\n\$clientdb_password='lab-db-root';\n");
file_put_contents('/usr/local/ispconfig/interface/lib/config.inc.local.php',"<?php\n\$conf['interface_base_url']='http://127.0.0.1:18080';\n");
$security=file_get_contents('/usr/local/ispconfig/security/security_settings.ini');
$security=str_replace(['ids_anon_enabled=yes','ids_user_enabled=yes'],['ids_anon_enabled=no','ids_user_enabled=no'],$security);
file_put_contents('/usr/local/ispconfig/security/security_settings.ini',$security);
echo "Seed/config ready for server $server\n";
