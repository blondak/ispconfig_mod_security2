<?php
require __DIR__.'/http.php';
$admin=new Browser();$admin->login('admin','Admin-Lab-123!');
$admin->request('/capp.php?mod=admin');
[$code,$nav]=$admin->request('/nav.php?nav=side');
expect($code===200 && str_contains($nav,'admin/modsecurity.php'),'System sidebar includes server settings');
[$code,$core]=$admin->request('/admin/server_config_edit.php?id=1&next_tab=server');
expect($code===200 && str_contains($core,"changeTab('modsecurity'"),'native Server Config has ModSecurity tab');
$corePost=nativeFields($core);$corePost['next_tab']='modsecurity';
// Save a valid fixture hostname through the core form before changing tabs.
$corePost['hostname']='server1.lab.test';
[$code,$posted]=$admin->request('/admin/server_config_edit.php',$corePost);
expect($code===200 && str_starts_with($posted,'HEADER_REDIRECT:admin/modsecurity.php?server_id=1'),'native core server POST enters ModSecurity after validation');

[$code,$bridge]=$admin->request('/admin/server_config_edit.php?id=1&next_tab=modsecurity');
expect($code===200 && $bridge==='HEADER_REDIRECT:admin/modsecurity.php?server_id=1','native server tab routes to extension');
[$code,$list]=$admin->request('/admin/modsecurity.php');
expect($code===200 && str_contains($list,'server1.lab.test') && str_contains($list,'server2.lab.test'),'admin can select both web servers');
$client=new Browser();$client->login('client1','Client1-Lab-123!');
[$code,$body]=$client->request('/admin/modsecurity.php?server_id=1');
expect($code===403 || $code===302 || !str_contains($body,'name="mode"'),'client cannot read administrator server controls');
function setServer(Browser $admin,int $server,string $mode,bool $override=false): void {
    [$code,$html]=$admin->request('/admin/modsecurity.php?server_id='.$server);
    expect($code===200 && str_contains($html,'name="server_id"'),'server '.$server.' settings open');
    $post=nativeFields($html);$post['mode']=$mode;
    if($override) $post['allow_override']='1';else unset($post['allow_override']);
    $post['override_body_limit']='1';$post['body_limit']='40';$post['override_nofiles_limit']='1';$post['nofiles_limit']='40';
    [$code,$html]=$admin->request('/admin/modsecurity.php',$post);
    $applied=false;
    for($i=0;$i<30;$i++) {
        [$code,$html]=$admin->request('/admin/modsecurity.php?server_id='.$server);
        if(str_contains($html,'<strong>Applied</strong>')) { $applied=true;break; }
        usleep(500000);
    }
    expect($applied,'server '.$server.' mode '.$mode.' configtest/reload and status');
}
function siteStatus(int $server): int {
    $curl=curl_init('http://server'.$server.':8081/?q=%3Cscript%3Ealert%281%29%3C%2Fscript%3E');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Host: web'.$server.'.lab.test']]);
    curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);return $code;
}
// Site desired configuration remains On from smoke.php on both nodes.
setServer($admin,1,'On');setServer($admin,2,'DetectionOnly');
expect(siteStatus(1)===403,'node1 really blocks a CRS XSS request');
expect(siteStatus(2)===200,'node2 DetectionOnly overrides saved site On and permits the same request');
[$code,$html]=$client->request('/sites/modsecurity.php?domain_id=1');
expect(str_contains($html,'The server controls the mode') && str_contains($html,'id="ms-mode" disabled'),'website UI explains and disables server-controlled mode');
$before=json_decode($db->query('SELECT config_json FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['config_json'],true);
$post=nativeFields($html);$post['mode']='Off';
[$code,$html]=$client->request('/sites/modsecurity.php',$post);
$after=json_decode($db->query('SELECT config_json FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['config_json'],true);
expect($before['mode']===$after['mode'],'forged website mode cannot override server policy');
setServer($admin,1,'Off');
expect(siteStatus(1)===200,'node1 Off stops blocking despite saved site On');
expect(siteStatus(2)===200,'node1 change leaves node2 DetectionOnly unchanged');
setServer($admin,1,'DetectionOnly',true);
expect(siteStatus(1)===403,'allowing website overrides restores saved site On over DetectionOnly default');
// Leave the demo in its original inherited-server state and preserve all site settings.
foreach([1,2] as $server) {
    [$code,$html]=$admin->request('/admin/modsecurity.php?server_id='.$server);$post=nativeFields($html);
    $post['mode']='inherit';$post['allow_override']='1';unset($post['override_body_limit'],$post['override_nofiles_limit']);
    $admin->request('/admin/modsecurity.php',$post);
}
foreach(['en'=>'These settings apply to the selected Apache server.', 'de'=>'Diese Einstellungen gelten für den ausgewählten Apache-Server.', 'cz'=>'Toto nastavení platí pro vybraný Apache server.'] as $language=>$text) {
    $q=$db->prepare('UPDATE sys_user SET language=? WHERE userid=1');$q->bind_param('s',$language);$q->execute();
    try {
        $localized=new Browser();$localized->login('admin','Admin-Lab-123!');$localized->request('/capp.php?mod=admin');
        [$code,$html]=$localized->request('/admin/modsecurity.php?server_id=1');
        expect($code===200 && str_contains(html_entity_decode($html,ENT_QUOTES,'UTF-8'),$text) && !str_contains($html,'{tmpl_var'),'server page localized in '.$language);
    } finally { $db->query("UPDATE sys_user SET language='en' WHERE userid=1"); }
}
echo "$count server integration assertions passed\n";
