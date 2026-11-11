<?php
// Exercise only a session created by this test; never edit existing users' sessions.
session_id('modsecurity-regression-'.bin2hex(random_bytes(12)));
session_start(['use_cookies'=>false,'cache_limiter'=>'','save_path'=>'/tmp']);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('db','root','lab-db-root','ispc_master');
$curl=curl_init();$jar=tempnam('/tmp','lab-stale-cookie-');
curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_CONNECT_TO=>['127.0.0.1:80:server1:80'],CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['X-Requested-With: XMLHttpRequest']]);
function callPage(string $path,?array $post=null): array {
    global $curl;
    curl_setopt($curl,CURLOPT_URL,'http://127.0.0.1'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);
    if($post!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));
    $body=curl_exec($curl);if($body===false)throw new RuntimeException(curl_error($curl));
    return [curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$body];
}
function checkStale(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message."\n"; }
try {
    callPage('/login/');[$code,$login]=callPage('/login/index.php',['username'=>'client1','password'=>'Client1-Lab-123!','s_mod'=>'login','s_pg'=>'index']);
    checkStale(str_contains($login,'LOGIN_REDIRECT:') || $code===302,'test client logged in');
    $sid='';foreach(curl_getinfo($curl,CURLINFO_COOKIELIST) as $cookie) { $parts=explode("\t",$cookie);if(($parts[5]??'')==='ISPCSESS')$sid=$parts[6]; }
    if(!$sid)throw new RuntimeException('No test session cookie');
    $hash=hash('sha256',$sid);$q=$db->prepare('SELECT session_data FROM sys_session WHERE session_id=?');$q->bind_param('s',$hash);$q->execute();$raw=$q->get_result()->fetch_assoc()['session_data'];
    $_SESSION=[];if(!session_decode($raw))throw new RuntimeException('Cannot decode own test session');
    $other=['plugin'=>'unrelated_lab_plugin','function'=>'unchanged','module'=>''];
    $_SESSION['s']['plugin_cache']=[
        'sites:web_vhost_domain:end_form'=>[['plugin'=>'modsecurity_link_plugin','function'=>'link','module'=>'']],
        'login'=>[['plugin'=>'modsecurity_link_plugin','function'=>'login','module'=>'']],
        'lab:unrelated'=>[$other],
    ];
    $serialized=session_encode();$_SESSION=[];
    $q=$db->prepare('UPDATE sys_session SET session_data=? WHERE session_id=?');$q->bind_param('ss',$serialized,$hash);$q->execute();
    [$code,$body]=callPage('/sites/web_vhost_domain_edit.php?id=1&type=domain');
    checkStale($code===200 && str_contains($body,'web1.lab.test'),'legacy cached link callback opens website without HTTP 500');
    checkStale(str_contains($body,'data-load-content="sites/modsecurity.php?domain_id=1"'),'first response has native fallback navigation');
    $q=$db->prepare('SELECT session_data FROM sys_session WHERE session_id=?');$q->bind_param('s',$hash);$q->execute();$raw=$q->get_result()->fetch_assoc()['session_data'];
    session_decode($raw);
    checkStale(!isset($_SESSION['s']['plugin_cache']['sites:web_vhost_domain:end_form']),'legacy callback removed from own session cache');
    checkStale(isset($_SESSION['s']['plugin_cache']['sites:web_vhost_domain:on_after_formdef']),'native tab hook registered in same session');
    checkStale($_SESSION['s']['plugin_cache']['lab:unrelated']===[$other],'other plugins preserved');
    $_SESSION=[];
    [$code,$body]=callPage('/sites/web_vhost_domain_edit.php?id=1&type=domain');
    checkStale($code===200 && str_contains($body,"changeTab('modsecurity'"),'following request contains native ModSecurity tab without re-login');
    [$code,$body]=callPage('/sites/web_vhost_domain_edit.php?id=2&type=domain');
    checkStale(!str_contains($body,'web2.lab.test') && !str_contains($body,"changeTab('modsecurity'"),'cache repair does not grant access to another client website');
} finally { curl_close($curl);@unlink($jar);$_SESSION=[];session_destroy(); }
echo "8 stale-session regression assertions passed\n";
