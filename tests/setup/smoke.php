<?php
// Run in a disposable app container on the Compose network; test credentials only.
require __DIR__.'/http.php';
$anon=new Browser();[$code]=$anon->request('/sites/modsecurity_diagnostics.php?domain_id=1');expect($code===302,'anonymous diagnostic access denied');
$admin=new Browser();$admin->login('admin','Admin-Lab-123!');
foreach([1,2] as $site) {
    [$code,$settings]=$admin->request('/sites/modsecurity.php?domain_id='.$site);
    expect($code===200 && str_contains($settings,'Administrator policy'),'admin opens settings on node '.$site);
    preg_match('/name="revision" value="([0-9]+)"/',$settings,$rev);
    $post=token($settings)+['_ms_action'=>'save','domain_id'=>$site,'revision'=>$rev[1],'enabled'=>'on','allow_disable'=>'on','max_body_limit'=>'40','max_nofiles_limit'=>'40','override_body_limit'=>'1','override_nofiles_limit'=>'1','body_limit'=>'40','nofiles_limit'=>'40','mode'=>'On','scope'=>['prefix'],'path'=>['/admin/'],'ids'=>['941100']];
    [$code,$settings]=$admin->request('/sites/modsecurity.php',$post);
    expect($code===200 && !str_contains($settings,'class="error"'),'admin saves limits and URL exclusion on node '.$site);
    $applied=false;
    for($i=0;$i<20;$i++) {
        [$code,$settings]=$admin->request('/sites/modsecurity.php?domain_id='.$site);
        if(str_contains($settings,'<strong>Applied</strong>')) { $applied=true;break; }
        usleep(500000);
    }
    expect($applied,'Apache configtest and real graceful reload succeeded on node '.$site);
}
// Checkbox state is authoritative even if a browser sends a stale numeric value.
[$code,$limitsHtml]=$admin->request('/sites/modsecurity.php?domain_id=1');
$limitsPost=nativeFields($limitsHtml);
unset($limitsPost['override_body_limit'],$limitsPost['override_nofiles_limit']);
$limitsPost['body_limit']='40';$limitsPost['nofiles_limit']='40';
[$code,$limitsHtml]=$admin->request('/sites/modsecurity.php',$limitsPost);
$storedLimits=json_decode($db->query('SELECT config_json FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['config_json'],true);
expect($code===200 && $storedLimits['body_limit']===null && $storedLimits['nofiles_limit']===null,'unchecked overrides inherit even when stale numeric values are submitted');
$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<html><body>'.$limitsHtml.'</body></html>');libxml_clear_errors();$xp=new DOMXPath($doc);
expect($xp->query('//input[@name="override_body_limit" and not(@checked)]')->length===1 && $xp->query('//input[@name="body_limit" and @disabled]')->length===1,'inherited limit renders unchecked toggle and disabled number');
$limitsPost=nativeFields($limitsHtml);$limitsPost['override_body_limit']='1';$limitsPost['body_limit']='40';
[$code,$limitsHtml]=$admin->request('/sites/modsecurity.php',$limitsPost);
$storedLimits=json_decode($db->query('SELECT config_json FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['config_json'],true);
expect($storedLimits['body_limit']===41943040 && $storedLimits['nofiles_limit']===null,'each limit can be overridden independently');
$beforeRevision=$db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision'];
$limitsPost=nativeFields($limitsHtml);$limitsPost['body_limit']='';
[$code,$invalidLimit]=$admin->request('/sites/modsecurity.php',$limitsPost);
expect(str_contains($invalidLimit,'each enabled override') && $db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision']===$beforeRevision,'checked override requires a value and does not save an empty limit');
[$code,$limitsHtml]=$admin->request('/sites/modsecurity.php?domain_id=1');
$limitsPost=nativeFields($limitsHtml);$limitsPost['override_body_limit']='1';$limitsPost['override_nofiles_limit']='1';$limitsPost['body_limit']='10';$limitsPost['nofiles_limit']='20';
[$code,$invalidLimit]=$admin->request('/sites/modsecurity.php',$limitsPost);
expect(str_contains($invalidLimit,'The limit excluding files exceeds') && $db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision']===$beforeRevision,'enabled overrides retain no-files versus total-limit validation');
[$code,$limitsHtml]=$admin->request('/sites/modsecurity.php?domain_id=1');
$limitsPost=nativeFields($limitsHtml);$limitsPost['override_body_limit']='1';$limitsPost['override_nofiles_limit']='1';$limitsPost['body_limit']='40';$limitsPost['nofiles_limit']='40';
[$code,$limitsHtml]=$admin->request('/sites/modsecurity.php',$limitsPost);
expect($code===200 && str_contains($limitsHtml,'data-ms-limit-for="ms-body_limit"'),'custom limits restored using native checkbox controls');
// Native website tab switching must save/validate core fields before entering the extension.
[$code,$core]=$admin->request('/sites/web_vhost_domain_edit.php?id=1&type=domain&next_tab=domain');
$corePost=nativeFields($core);$corePost['next_tab']='modsecurity';
$beforeCore=$db->query('SELECT domain,server_id,document_root,system_user,system_group FROM web_domain WHERE domain_id=1')->fetch_assoc();
[$code,$redirect]=$admin->request('/sites/web_vhost_domain_edit.php',$corePost);
expect($code===200 && str_starts_with($redirect,'HEADER_REDIRECT:sites/modsecurity.php?domain_id=1'),'core form POST validates before native ModSecurity tab');
$afterCore=$db->query('SELECT domain,server_id,document_root,system_user,system_group FROM web_domain WHERE domain_id=1')->fetch_assoc();
expect($beforeCore===$afterCore,'switching tabs preserves core website identity and paths');
[$code,$core]=$admin->request('/sites/web_vhost_domain_edit.php?id=1&type=domain&next_tab=domain');
$corePost=nativeFields($core);$corePost['domain']='invalid domain!';$corePost['next_tab']='modsecurity';
[$code,$invalid]=$admin->request('/sites/web_vhost_domain_edit.php',$corePost);
expect(!str_starts_with($invalid,'HEADER_REDIRECT:') && str_contains($invalid,'alert-danger'),'invalid core fields stay on original tab for correction');
expect($db->query('SELECT domain FROM web_domain WHERE domain_id=1')->fetch_assoc()['domain']===$beforeCore['domain'],'invalid tab switch does not mutate website domain');
foreach([1,2] as $client) {
    $b=new Browser();$b->login('client'.$client,'Client'.$client.'-Lab-123!');
    [$code,$body]=$b->request('/sites/modsecurity_diagnostics.php?domain_id='.$client);
    expect($code===200 && str_contains($body,'web'.$client.'.lab.test'),'client sees own website');
    expect(!str_contains($body,'web'.(3-$client).'.lab.test'),'client website selector hides other client');
    [$code]=$b->request('/sites/modsecurity_diagnostics.php?domain_id='.(3-$client));expect($code===403,'cross-client direct ID rejected');
    [$code,$nav]=$b->request('/nav.php?nav=side');expect(str_contains($nav,'modsecurity_diagnostics.php'),'sidebar contains diagnostics link');
    [$code,$edit]=$b->request('/sites/web_vhost_domain_edit.php?id='.$client.'&type=domain');
    expect($code===200 && str_contains($edit,"changeTab('modsecurity'"),'website edit contains native ModSecurity tab');
    $clientPost=nativeFields($edit);$clientPost['next_tab']='modsecurity';
    $clientBefore=$db->query('SELECT domain,server_id,document_root,system_user,system_group FROM web_domain WHERE domain_id='.$client)->fetch_assoc();
    [$code,$clientBridge]=$b->request('/sites/web_vhost_domain_edit.php',$clientPost);
    expect($code===200 && str_starts_with($clientBridge,'HEADER_REDIRECT:sites/modsecurity.php?domain_id='.$client),'client core tab POST enters ModSecurity');
    expect($clientBefore===$db->query('SELECT domain,server_id,document_root,system_user,system_group FROM web_domain WHERE domain_id='.$client)->fetch_assoc(),'client tab transition preserves core website fields');
    [$code,$bridge]=$b->request('/sites/web_vhost_domain_edit.php?id='.$client.'&type=domain&next_tab=modsecurity');
    expect($code===200 && $bridge==='HEADER_REDIRECT:sites/modsecurity.php?domain_id='.$client,'native website tab enters extension controller');
    [$code,$native]=$b->request('/sites/modsecurity.php?domain_id='.$client);
    expect($code===200 && !str_contains(strtolower($native),'<html') && !str_contains(strtolower($native),'<style') && !str_contains(strtolower($native),'<form'),'settings use native pageForm without nested document');
    expect(str_contains($native,'data-submit-form="pageForm"') && str_contains($native,'nav nav-tabs') && str_contains($native,'nav nav-tabs ms-subtabs'),'settings use native actions and website tabs');
    // Make repeats deterministic only in the dedicated lab database.
    $db->query('UPDATE modsecurity_diagnostic_request SET requested_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE domain_id='.$client);
    [$code,$body]=$b->request('/sites/modsecurity_diagnostics.php?domain_id='.$client);
    $before=$db->query('SELECT revision FROM modsecurity_diagnostic_request WHERE domain_id='.$client)->fetch_assoc();
    $post=token($body);$post['domain_id']=$client;$post['_ms_action']='collect';
    [$code,$body]=$b->request('/sites/modsecurity_diagnostics.php',$post);
    $after=$db->query('SELECT revision FROM modsecurity_diagnostic_request WHERE domain_id='.$client)->fetch_assoc();
    expect($code===200 && (int)($after['revision']??0)===(int)($before['revision']??0)+1,'diagnostic request accepted with real CSRF token');
    $ready=false;
    for($i=0;$i<20;$i++) {
        [$code,$body]=$b->request('/sites/modsecurity_diagnostics.php?domain_id='.$client);
        if(str_contains($body,'<strong>Collected</strong>') && str_contains($body,'lab-server'.$client.'-request-xss')) { $ready=true;break; }
        usleep(500000);
    }
    expect($ready,'node '.$client.' collected root-owned error log via datalog');
    expect(str_contains($body,'941100') && str_contains($body,'949110') && str_contains($body,'The original detection rule'),'detections and summary-only request visible');
    expect(!str_contains($body,'lab-server'.(3-$client).'-request-'),'snapshot belongs to correct node');
    [$code,$filtered]=$b->request('/sites/modsecurity_diagnostics.php?domain_id='.$client.'&rule_id=942100');
    expect(str_contains($filtered,'/search') && !str_contains($filtered,'lab-server'.$client.'-request-xss'),'rule filter works');
    [$code,$blocked]=$b->request('/sites/modsecurity_diagnostics.php?domain_id='.$client.'&blocked=1');
    expect(!str_contains($blocked,'lab-server'.$client.'-request-warning'),'blocked-only filter excludes warning');
    // No exception to the site's read ACL is granted by a POST request.
    [$code]=$b->request('/sites/modsecurity_diagnostics.php',['domain_id'=>3-$client]+token($body));expect($code===403,'cross-client POST rejected');
}
[$code,$body]=$admin->request('/sites/modsecurity_diagnostics.php');
expect($code===200 && str_contains($body,'web1.lab.test') && str_contains($body,'web2.lab.test'),'admin sees both websites');
$local=new mysqli('db','root','lab-db-root','ispc_node2');
expect($local->query('SELECT * FROM modsecurity_diagnostic_request WHERE domain_id=2 AND server_id=2')->num_rows===1,'request really replicated into node2 database');
expect($db->query('SELECT * FROM server WHERE server_id IN (1,2) AND updated>0')->num_rows===2,'both ISPConfig nodes advanced datalog cursors');
// The capability lives in the native client Limits form, not in per-site POST fields.
$admin->request('/capp.php?mod=client');
function setCapability(Browser $admin,mysqli $db,int $client,bool $enabled): void {
    [$code,$html]=$admin->request('/client/client_edit.php?id='.$client.'&next_tab=limits');
    expect($code===200 && str_contains(html_entity_decode($html,ENT_QUOTES,'UTF-8'),'Allow changes to ModSecurity settings') && str_contains($html,"input.attr('type','checkbox')"),'native client limits include ModSecurity toggle');
    $post=nativeFields($html);$post['next_tab']='limits';
    if($enabled) $post['limit_modsecurity']='y';else unset($post['limit_modsecurity']);
    [$code,$html]=$admin->request('/client/client_edit.php',$post);
    $value=$db->query('SELECT limit_modsecurity FROM client WHERE client_id='.$client)->fetch_assoc()['limit_modsecurity'];
    expect($code===200 && $value===($enabled?'y':'n'),'native client limit persisted '.($enabled?'enabled':'disabled'));
}
setCapability($admin,$db,1,false);
setCapability($admin,$db,2,false);
$limited=new Browser();$limited->login('client1','Client1-Lab-123!');
[$code,$html]=$limited->request('/sites/modsecurity.php?domain_id=1');
expect(str_contains($html,'Settings are read-only'),'disabled client sees read-only settings');
$clientPost=nativeFields($html);$clientPost['enabled']='on';$clientPost['limit_modsecurity']='y';
$beforeRevision=$db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision'];
[$code,$html]=$limited->request('/sites/modsecurity.php',$clientPost);
expect($db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision']===$beforeRevision,'disabled capability cannot be bypassed by direct POST or per-site enabled flag');
[$code,$diagnostic]=$limited->request('/sites/modsecurity_diagnostics.php?domain_id=1');
expect($code===200 && str_contains($diagnostic,'lab-server1-request'),'diagnostics remain readable without write capability');
setCapability($admin,$db,1,true);
[$code,$html]=$limited->request('/sites/modsecurity.php?domain_id=1');$clientPost=nativeFields($html);
[$code,$html]=$limited->request('/sites/modsecurity.php',$clientPost);
$afterRevision=$db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision'];
expect((int)$afterRevision===(int)$beforeRevision+1,'enabled client can save without logging in again');
[$code,$html]=$limited->request('/sites/modsecurity.php?domain_id=1');$stalePost=nativeFields($html);
setCapability($admin,$db,1,false);
[$code,$html]=$limited->request('/sites/modsecurity.php',$stalePost);
expect($db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision']===$afterRevision,'revocation blocks an already-open form in existing session');
setCapability($admin,$db,1,true);
$admin->request('/capp.php?mod=sites');
// Render the actual controllers in all supported ISPConfig account languages.
foreach(['en'=>['Administrator policy','Settings','Diagnostics','Override limit','Allow changes to ModSecurity settings','The limit excluding files exceeds'],
         'de'=>['Administratorrichtlinie','Einstellungen','Diagnose','Limit überschreiben','Änderungen an ModSecurity-Einstellungen erlauben','Das Limit ohne Dateien überschreitet'],
         'cz'=>['Administrátorská politika','Nastavení','Diagnostika','Změnit limit','Povolit změny nastavení ModSecurity','Limit bez souborů překračuje']] as $language=>$labels) {
    $q=$db->prepare('UPDATE sys_user SET language=? WHERE userid=1');$q->bind_param('s',$language);$q->execute();
    try {
        $localized=new Browser();$localized->login('admin','Admin-Lab-123!');
        [$code,$html]=$localized->request('/sites/modsecurity.php?domain_id=1');$htmlDecoded=html_entity_decode($html,ENT_QUOTES,'UTF-8');
        expect($code===200 && str_contains($htmlDecoded,$labels[0]) && str_contains($htmlDecoded,$labels[1]) && str_contains($htmlDecoded,$labels[2]) && str_contains($htmlDecoded,$labels[3]) && !str_contains($html,'{tmpl_var'),'settings and tabs localized in '.$language);
        $invalid=nativeFields($html);$invalid['override_body_limit']='1';$invalid['override_nofiles_limit']='1';$invalid['body_limit']='10';$invalid['nofiles_limit']='20';
        [$code,$html]=$localized->request('/sites/modsecurity.php',$invalid);
        expect(str_contains(html_entity_decode($html,ENT_QUOTES,'UTF-8'),$labels[5]),'server validation localized in '.$language);
        [$code,$html]=$localized->request('/sites/modsecurity_diagnostics.php?domain_id=1');
        expect($code===200 && str_contains(html_entity_decode($html,ENT_QUOTES,'UTF-8'),$labels[2]) && str_contains($html,'lab-server1-request'),'diagnostics localized in '.$language);
        $localized->request('/capp.php?mod=client');
        [$code,$html]=$localized->request('/client/client_edit.php?id=1&next_tab=limits');
        expect($code===200 && str_contains(html_entity_decode($html,ENT_QUOTES,'UTF-8'),$labels[4]),'native client limits localized in '.$language);
    } finally { $db->query("UPDATE sys_user SET language='en' WHERE userid=1"); }
}
echo "$count end-to-end assertions passed\n";
