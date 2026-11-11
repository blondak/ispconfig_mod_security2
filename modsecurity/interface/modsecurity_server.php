<?php
require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once '/usr/local/ispconfig/extensions/modsecurity/lib/ServerConfig.php';
require_once '/usr/local/ispconfig/extensions/modsecurity/lib/Store.php';
use IspModsecurity\I18n;
use IspModsecurity\ServerConfig;
$app->auth->check_module_permissions('admin');
$app->auth->check_security_permissions('admin_allow_server_config');
if(($_SESSION['s']['user']['typ']??'')!=='admin') { http_response_code(403);exit(I18n::t('access_denied')); }
$app->uses('tpl,tform');
$servers=$app->db->queryAllRecords('SELECT server_id,server_name FROM server WHERE web_server=1 ORDER BY server_name');
$id=(int)($_POST['server_id']??$_GET['server_id']??0);$server=null;
foreach($servers as $item) if((int)$item['server_id']===$id) $server=$item;
if($id && !$server) { http_response_code(404);exit(I18n::t('no_web_server')); }
$row=$server?$app->db->queryOneRecord('SELECT * FROM modsecurity_server_config WHERE server_id=?',$id):null;
$config=$row?json_decode($row['config_json'],true,512,JSON_THROW_ON_ERROR):ServerConfig::defaults();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_ms_action']??'')==='save') {
    $app->auth->csrf_token_check();
    $name='modsecurity_server_'.$id;
    $lock=$app->db->queryOneRecord('SELECT GET_LOCK(?,10) AS acquired',$name);
    if((int)($lock['acquired']??0)!==1) exit(I18n::t('settings_busy'));
    try {
        if(!$server) throw new RuntimeException(I18n::t('select_server'));
        $row=$app->db->queryOneRecord('SELECT * FROM modsecurity_server_config WHERE server_id=?',$id);
        if((int)($_POST['revision']??0)!==(int)($row['revision']??0)) throw new RuntimeException(I18n::t('conflict'));
        $candidate=['mode'=>$_POST['mode']??'inherit','allow_override'=>isset($_POST['allow_override'])];
        foreach(['body_limit','nofiles_limit'] as $key) {
            $candidate[$key]=null;
            if(($_POST['override_'.$key]??'')!=='1') continue;
            if(($_POST[$key]??'')==='') throw new RuntimeException(I18n::t('limit_required'));
            $candidate[$key]=ServerConfig::mib($_POST[$key]);
        }
        $config=ServerConfig::validate($candidate);
        $next=['server_id'=>$id,'revision'=>(int)($row['revision']??0)+1,'config_json'=>json_encode($config,JSON_THROW_ON_ERROR),'updated_by'=>$_SESSION['s']['user']['username'],'updated_at'=>date('Y-m-d H:i:s')];
        \IspModsecurity\Store::save($app->db,'modsecurity_server_config',$next,(bool)$row);$row=$next;
    } catch(Throwable $e) { $error=$e->getMessage(); }
    finally { $app->db->query('SELECT RELEASE_LOCK(?)',$name); }
}
$status=$server?$app->db->queryOneRecord('SELECT * FROM modsecurity_server_status WHERE server_id=?',$id):null;
$state=!$row?'inherited':(!$status || (int)$status['revision']!==(int)$row['revision']?'pending':$status['state']);
$token=$app->auth->csrf_token_get('modsecurity_server');
$vars=I18n::templateVars()+[
    'has_server'=>(bool)$server,'server_id'=>$id,'server_name'=>$server['server_name']??'','error'=>$error,
    'revision'=>(int)($row['revision']??0),'applied_revision'=>(int)($status['applied_revision']??0),
    'status_label'=>I18n::t($state),'status_class'=>['inherited'=>'info','pending'=>'info','applied'=>'success','error'=>'danger'][$state]??'danger',
    'status_message'=>I18n::message($status['message']??''),'allow_override'=>!empty($config['allow_override']),
    'csrf_id'=>$token['csrf_id'],'csrf_key'=>$token['csrf_key'],'settings_url'=>'admin/modsecurity.php?server_id='.$id,
];
$tabs=[];$modes=[];$limits=[];
if($server) {
    $app->tform->loadFormDef('form/server_config.tform.php');
    foreach($app->tform->formDef['tabs'] as $name=>$tab) if($name!=='modsecurity') $tabs[]=['title'=>$app->tform->lng($tab['title']),'url'=>'admin/server_config_edit.php?id='.$id.'&next_tab='.$name];
    foreach(['inherit'=>'inherit','On'=>'on','DetectionOnly'=>'detection_only','Off'=>'off'] as $value=>$key) $modes[]=['value'=>$value,'label'=>I18n::t($key),'selected'=>$config['mode']===$value];
    foreach(['body_limit','nofiles_limit'] as $key) $limits[]=['name'=>$key,'label'=>I18n::t($key),'value'=>$config[$key]===null?'':$config[$key]/1048576,'override'=>$config[$key]!==null];
}
$escape=function($v)use(&$escape) { return is_array($v)?array_map($escape,$v):(is_string($v)?htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):$v); };
$app->tpl->newTemplate('templates/modsecurity_server.htm');
foreach($escape($vars) as $key=>$value) $app->tpl->setVar($key,$value);
foreach(['server_list'=>$servers,'server_tabs'=>$tabs,'modes'=>$modes,'body_limits'=>$limits] as $key=>$value) $app->tpl->setLoop($key,$escape($value));
$app->tpl->pparse();
