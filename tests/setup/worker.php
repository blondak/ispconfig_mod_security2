<?php
chdir('/usr/local/ispconfig/server');
require 'lib/config.inc.php';
require 'lib/app.inc.php';
$app->uses('modules,plugins');
$app->modules->loadModules('all');
$app->plugins->announceEvents('lab_web', ['web_domain_delete']);
$app->plugins->loadPlugins('all');
$server=$app->db->queryOneRecord('SELECT * FROM server WHERE server_id=?',$conf['server_id']);
$conf['last_datalog_id']=(int)$server['updated'];
$conf['mirror_server_id']=0;
if(($argv[1]??'')==='--restore') {
    // DB is persistent, container filesystem is disposable: restore the already saved settings.
    foreach($app->db->queryAllRecords('SELECT * FROM modsecurity_server_config WHERE server_id=?',$conf['server_id']) as $row) {
        $app->loaded_modules['modsecurity_module']->server('modsecurity_server_config','u',['new'=>$row,'old'=>[]]);
    }
    foreach($app->db->queryAllRecords('SELECT * FROM modsecurity_config WHERE server_id=?',$conf['server_id']) as $row) {
        $app->loaded_modules['modsecurity_module']->process('modsecurity_config','u',['new'=>$row,'old'=>[]]);
    }
}
$app->modules->processDatalog();
