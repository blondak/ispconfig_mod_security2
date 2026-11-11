<?php
require __DIR__.'/http.php';
$b=new Browser();$b->login('client1','Client1-Lab-123!');
[$code,$html]=$b->request('/sites/modsecurity.php?domain_id=1');
expect($code===200 && str_contains($html,'value="regex"'),'website form offers regular-expression scope');
$original=nativeFields($html);
$pattern='^/livewire-[a-fA-F0-9]+/update$';
try {
    $post=$original;
    $post['scope']=['regex'];$post['path']=[$pattern];$post['ids']=['941100 941130'];$post['note']=['Livewire regex test'];unset($post['off']);
    [$code,$html]=$b->request('/sites/modsecurity.php',$post);
    $row=$db->query('SELECT revision,config_json FROM modsecurity_config WHERE domain_id=1')->fetch_assoc();
    $config=json_decode($row['config_json'],true);
    expect($code===200 && $config['exceptions'][0]['scope']==='regex' && $config['exceptions'][0]['path']===$pattern && $config['exceptions'][0]['ids']===[941100,941130],'client can save regex and multiple rule IDs');
    expect(str_contains($html,'value="regex" selected') && str_contains($html,$pattern),'saved regex reloads with correct selected scope');
    $applied=false;
    for($i=0;$i<20;$i++) {
        [$code,$html]=$b->request('/sites/modsecurity.php?domain_id=1');
        if(str_contains($html,'<strong>Applied</strong>')) { $applied=true;break; }
        usleep(500000);
    }
    expect($applied,'regex is applied by worker with actual Apache configtest/reload');
    $post=nativeFields($html);$post['path'][0]='^/livewire-[';
    [$code,$invalid]=$b->request('/sites/modsecurity.php',$post);
    expect(str_contains($invalid,'Invalid regular expression syntax.') && $db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision']===$row['revision'],'invalid regex cannot change saved configuration or revision');
    [$code,$html]=$b->request('/sites/modsecurity.php?domain_id=1');$post=nativeFields($html);$post['path'][0]='^/foo"';
    [$code,$invalid]=$b->request('/sites/modsecurity.php',$post);
    expect(str_contains($invalid,'Regex must start with') && $db->query('SELECT revision FROM modsecurity_config WHERE domain_id=1')->fetch_assoc()['revision']===$row['revision'],'Apache argument injection is rejected without saving');
} finally {
    [$code,$html]=$b->request('/sites/modsecurity.php?domain_id=1');
    preg_match('/name="revision" value="([0-9]+)"/',$html,$revision);
    $original=array_replace($original,token($html));$original['revision']=$revision[1];
    $b->request('/sites/modsecurity.php',$original);
}
echo "$count regex settings integration assertions passed\n";
