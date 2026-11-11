<?php
require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/I18n.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/Store.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/View.php';
$app->auth->check_module_permissions('sites');
header('Cache-Control: no-store');
$app->uses('tform');
$auth=$app->tform->getAuthSQL('r','web_domain');
$webs=$app->db->queryAllRecords("SELECT domain_id,domain,server_id,type FROM web_domain WHERE type IN ('vhost','vhostsubdomain','vhostalias') AND $auth ORDER BY domain");
$id=(int)($_POST['domain_id']??$_GET['domain_id']??$_POST['id']??$_GET['id']??0); $web=null;
foreach($webs as $w) if((int)$w['domain_id']===$id) $web=$w;
if($id && !$web) { http_response_code(403); exit(\IspModsecurity\I18n::t('access_denied')); }
function msd_h($s) { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_REQUEST['_ms_action']??'')==='collect') {
    $app->auth->csrf_token_check();
    $name='modsecurity_diag_'.$id;
    $lock=$app->db->queryOneRecord('SELECT GET_LOCK(?,10) AS acquired',$name);
    if((int)($lock['acquired']??0)!==1) exit(\IspModsecurity\I18n::t('diagnostics_busy'));
    try {
        if(!$web) throw new RuntimeException(\IspModsecurity\I18n::t('select_website'));
        // A single shared throttle per website also covers simultaneous sessions/users.
        $old=$app->db->queryOneRecord('SELECT *, TIMESTAMPDIFF(SECOND,requested_at,NOW()) AS age FROM modsecurity_diagnostic_request WHERE domain_id = ?',$id);
        if($old && (int)$old['age']<30) throw new RuntimeException(\IspModsecurity\I18n::t('throttle'));
        $now=$app->db->queryOneRecord('SELECT NOW() AS requested_at');
        $row=['domain_id'=>$id,'server_id'=>(int)$web['server_id'],'revision'=>(int)($old['revision']??0)+1,'requested_at'=>$now['requested_at'],'requested_by'=>$_SESSION['s']['user']['username']];
        \IspModsecurity\Store::save($app->db,'modsecurity_diagnostic_request',$row,(bool)$old);
        if($app->db->errorNumber) throw new RuntimeException(\IspModsecurity\I18n::t('request_failed'));
    } catch(Throwable $e) { $error=$e->getMessage(); }
    finally { $app->db->query('SELECT RELEASE_LOCK(?)',$name); }
}
$request=$web?$app->db->queryOneRecord('SELECT * FROM modsecurity_diagnostic_request WHERE domain_id = ?',$id):null;
$result=$web?$app->db->queryOneRecord('SELECT * FROM modsecurity_diagnostic_result WHERE domain_id = ? AND server_id = ?',$id,$web['server_id']):null;
$data=$result?json_decode($result['result_json'],true):[];
$events=$data['events']??[];
$pending=$request && (!$result || (int)$request['revision']!==(int)$result['revision']);
$filterId=trim((string)($_REQUEST['rule_id']??'')); $filterPath=trim((string)($_REQUEST['path']??''));
$blocked=($_REQUEST['blocked']??'')==='1';
$token=$app->auth->csrf_token_get('modsecurity_diagnostics');

$shown=[];
foreach($events as $event) {
    if($blocked && !$event['blocked']) continue;
    if($filterPath!=='' && stripos($event['uri'],$filterPath)===false) continue;
    if($filterId!=='' && !in_array($filterId,array_map('strval',array_column($event['rules'],'id')),true)) continue;
    $event['unique_id']=$event['unique_id']?:\IspModsecurity\I18n::t('missing_unique_id');
    $event['uri']=$event['uri']?:\IspModsecurity\I18n::t('missing_path');
    $event['detection_ids']=implode(', ',$event['detection_ids']);
    $shown[]=$event;
}
$state=$pending?'pending':(!$result?'none':$result['state']);
\IspModsecurity\View::render('diagnostics',$web,$webs,[
    'error'=>$error,'csrf_id'=>$token['csrf_id'],'csrf_key'=>$token['csrf_key'],'events'=>$shown,
    'status_label'=>['pending'=>\IspModsecurity\I18n::t('waiting_node'),'none'=>\IspModsecurity\I18n::t('not_collected'),'ready'=>\IspModsecurity\I18n::t('collected'),'error'=>\IspModsecurity\I18n::t('error')][$state]??\IspModsecurity\I18n::t('error'),
    'status_class'=>['pending'=>'info','none'=>'info','ready'=>'success','error'=>'danger'][$state]??'danger',
    'pending'=>$pending,'collected_at'=>$result['collected_at']??'','result_message'=>\IspModsecurity\I18n::message($result['message']??''),
    'filter_id'=>$filterId,'filter_path'=>$filterPath,'blocked'=>$blocked,'truncated'=>!empty($data['truncated']),
    'empty_result'=>!$shown && $result && $result['state']==='ready',
    'refresh_url'=>'sites/modsecurity_diagnostics.php?'.http_build_query(['domain_id'=>$id,'rule_id'=>$filterId,'path'=>$filterPath,'blocked'=>$blocked?'1':'0']),
]);
