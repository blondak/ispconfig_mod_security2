<?php
require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/I18n.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/Config.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/Store.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/View.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/Access.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/ServerConfig.php';
$app->auth->check_module_permissions('sites');
$app->uses('tform');
$admin=($_SESSION['s']['user']['typ']??'')==='admin';
$auth=$app->tform->getAuthSQL('u','web_domain');
$webs=$app->db->queryAllRecords("SELECT * FROM web_domain WHERE type IN ('vhost','vhostsubdomain','vhostalias') AND $auth ORDER BY domain");
$id=(int)($_POST['domain_id']??$_GET['domain_id']??$_POST['id']??$_GET['id']??0);
$web=null; foreach($webs as $w) if((int)$w['domain_id']===$id) $web=$w;
function ms_h($s) { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$policy=['enabled'=>false,'allow_disable'=>false,'max_body_limit'=>41943040,'max_nofiles_limit'=>41943040];
$c=\IspModsecurity\Config::defaults(); $row=null; $error='';
if($id && !$web) { http_response_code(403); exit(\IspModsecurity\I18n::t('access_denied')); }
if($web) {
    $row=$app->db->queryOneRecord('SELECT * FROM modsecurity_config WHERE domain_id = ?',$id);
    if($row) { $policy=json_decode($row['policy_json'],true); $c=json_decode($row['config_json'],true); }
}
$serverConfig=$web?\IspModsecurity\ServerConfig::forServer((int)$web['server_id']):\IspModsecurity\ServerConfig::defaults();
$serverModeLocked=\IspModsecurity\ServerConfig::locksMode($serverConfig);
$managementEnabled=$web?\IspModsecurity\Access::clientMayManage($web):false;
$policy['enabled']=$managementEnabled;
if($_SERVER['REQUEST_METHOD']==='POST' && ($_REQUEST['_ms_action']??'')==='save') {
    $app->auth->csrf_token_check();
    $lockName='modsecurity_web_'.$id;
    $locked=$app->db->queryOneRecord('SELECT GET_LOCK(?,10) AS acquired',$lockName);
    if((int)($locked['acquired']??0)!==1) exit(\IspModsecurity\I18n::t('settings_busy'));
    $row=$app->db->queryOneRecord('SELECT * FROM modsecurity_config WHERE domain_id = ?',$id);
    if($row) $policy=json_decode($row['policy_json'],true);
    $policy['enabled']=$web?\IspModsecurity\Access::clientMayManage($web):false;
    try {
        if(!$web) throw new RuntimeException(\IspModsecurity\I18n::t('select_website'));
        if((int)($_POST['revision']??0)!==(int)($row['revision']??0)) throw new RuntimeException(\IspModsecurity\I18n::t('conflict'));
        $mib=function($v) { if($v==='') return null; if(!preg_match('/^[0-9]+(?:\.[0-9]{1,6})?$/D',(string)$v)) throw new RuntimeException(\IspModsecurity\I18n::t('invalid_mib')); return (int)round((float)$v*1048576); };
        if($admin) {
            $policy=['enabled'=>$policy['enabled'],'allow_disable'=>isset($_POST['allow_disable']),'max_body_limit'=>$mib($_POST['max_body_limit']??'40'),'max_nofiles_limit'=>$mib($_POST['max_nofiles_limit']??'40')];
            foreach(['max_body_limit','max_nofiles_limit'] as $key) if(!$policy[$key] || $policy[$key]>1073741824) throw new RuntimeException(\IspModsecurity\I18n::t('invalid_max'));
        }
        $exceptions=[];
        foreach(($_POST['scope']??[]) as $i=>$scope) {
            $ids=trim((string)($_POST['ids'][$i]??'')); $off=isset($_POST['off'][$i]); $path=trim((string)($_POST['path'][$i]??''));
            if($ids==='' && !$off && $path==='') continue;
            if($ids!=='' && !preg_match('/^[0-9 ,]+$/D',$ids)) throw new RuntimeException(\IspModsecurity\I18n::t('invalid_ids_separator'));
            $exceptions[]=['scope'=>$scope,'path'=>$path,'ids'=>$ids===''?[]:array_map('intval',preg_split('/[ ,]+/',$ids)),'off'=>$off,'note'=>$_POST['note'][$i]??''];
        }
        $limits=[];
        foreach(['body_limit','nofiles_limit'] as $key) {
            if(($_POST['override_'.$key]??'')!=='1') { $limits[$key]=null; continue; }
            if(($_POST[$key]??'')==='') throw new RuntimeException(\IspModsecurity\I18n::t('limit_required'));
            $limits[$key]=$mib($_POST[$key]);
        }
        $c=\IspModsecurity\Config::validate(['mode'=>$serverModeLocked?$c['mode']:($_POST['mode']??'inherit'),'body_limit'=>$limits['body_limit'],'nofiles_limit'=>$limits['nofiles_limit'],'exceptions'=>$exceptions],$policy,$admin);
        // Revoking disable permission must not retain a disabled desired configuration.
        if($admin && !$policy['allow_disable'] && $policy['enabled']) \IspModsecurity\Config::validate($c,$policy,false);
        $new=['domain_id'=>$id,'server_id'=>(int)$web['server_id'],'revision'=>(int)($row['revision']??0)+1,'config_json'=>json_encode($c,JSON_THROW_ON_ERROR),'policy_json'=>json_encode($policy,JSON_THROW_ON_ERROR),'updated_by'=>$_SESSION['s']['user']['username'],'updated_at'=>date('Y-m-d H:i:s')];
        \IspModsecurity\Store::save($app->db,'modsecurity_config',$new,(bool)$row);
        $row=$new;
    } catch(Throwable $e) { $error=$e->getMessage(); }
    finally { $app->db->query('SELECT RELEASE_LOCK(?)',$lockName); }
}
$token=$app->auth->csrf_token_get('modsecurity');
$status=$web?$app->db->queryOneRecord('SELECT * FROM modsecurity_status WHERE domain_id = ? AND server_id = ?',$id,$web['server_id']):null;

$state=!$row?'inherit':(!$status || (int)$status['revision']!==(int)$row['revision']?'pending':$status['state']);
$vars=[
    'server_mode_locked'=>$serverModeLocked,'error'=>$error,'is_admin'=>$admin,'policy_enabled'=>$policy['enabled'],'policy_disable'=>$policy['allow_disable'],
    'owner_client_id'=>$web?\IspModsecurity\Access::ownerClientId($web):0,
    'can_edit'=>$admin || $policy['enabled'],'csrf_id'=>$token['csrf_id'],'csrf_key'=>$token['csrf_key'],
    'revision'=>(int)($row['revision']??0),'applied_revision'=>(int)($status['applied_revision']??0),
    'status_label'=>['inherit'=>\IspModsecurity\I18n::t('inherited'),'pending'=>\IspModsecurity\I18n::t('pending'),'applied'=>\IspModsecurity\I18n::t('applied'),'error'=>\IspModsecurity\I18n::t('error')][$state]??\IspModsecurity\I18n::t('error'),
    'status_class'=>['inherit'=>'info','pending'=>'info','applied'=>'success','error'=>'danger'][$state]??'danger',
    'status_message'=>\IspModsecurity\I18n::message($status['message']??''), 'modes'=>[], 'body_limits'=>[], 'policy_limits'=>[], 'exceptions'=>[],
];
foreach(['inherit'=>\IspModsecurity\I18n::t('inherit'),'On'=>\IspModsecurity\I18n::t('on'),'DetectionOnly'=>\IspModsecurity\I18n::t('detection_only'),'Off'=>\IspModsecurity\I18n::t('off')] as $value=>$label) $vars['modes'][]=['value'=>$value,'label'=>$label,'selected'=>($serverModeLocked?$serverConfig['mode']:$c['mode'])===$value,'disabled'=>!$admin && !$policy['allow_disable'] && in_array($value,['Off','DetectionOnly'],true)];
foreach(['body_limit'=>\IspModsecurity\I18n::t('body_limit'),'nofiles_limit'=>\IspModsecurity\I18n::t('nofiles_limit')] as $key=>$label) {
    $vars['body_limits'][]=['name'=>$key,'label'=>$label,'value'=>$c[$key]===null?'':$c[$key]/1048576,'override'=>$c[$key]!==null];
    $vars['policy_limits'][]=['name'=>'max_'.$key,'label'=>\IspModsecurity\I18n::t('maximum').$label,'value'=>$policy['max_'.$key]/1048576];
}
$rows=array_merge($c['exceptions'],array_fill(0,2,['scope'=>'exact','path'=>'','ids'=>[],'off'=>false,'note'=>'']));
foreach($rows as $index=>$r) $vars['exceptions'][]=['index'=>$index,'site'=>$r['scope']==='site','exact'=>$r['scope']==='exact','prefix'=>$r['scope']==='prefix','path'=>$r['path'],'ids'=>implode(' ',$r['ids']),'off'=>$r['off'],'note'=>$r['note'],'allow_disable'=>$admin || $policy['allow_disable']];
\IspModsecurity\View::render('settings',$web,$webs,$vars);
