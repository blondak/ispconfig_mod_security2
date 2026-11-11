<?php
require_once __DIR__.'/../modsecurity/lib/Compat.php';
require __DIR__.'/../modsecurity/lib/Diagnostics.php';
use IspModsecurity\Diagnostics;
$count=0;
function check($v,$why) { global $count; if(!$v) throw new RuntimeException($why); ++$count; echo "ok $count - $why\n"; }
function lineFor($id,$msg,$unique='request-1',$denied=false,$uri='/livewire-006d7c12/update') {
 return '[Wed Nov 11 10:34:20.766174 2026] [security2:error] [client 145.14.232.132] ModSecurity: '.($denied?'Access denied with code 403 (phase 2).':'Warning.').' [id "'.$id.'"] [msg "'.$msg.'"] [severity "CRITICAL"] [hostname "test.example.cz"] [uri "'.$uri.'"] [unique_id "'.$unique.'"]';
}
$example=lineFor(949110,'Inbound Anomaly Score Exceeded (Total Score: 5)','request-1',true)."\n".lineFor(980130,'Inbound Anomaly Score Exceeded (Total Inbound Score: 5 - SQLI=0,XSS=5)','request-1')."\n";
$e=Diagnostics::parse($example);
check(count($e)===1,'sample log lines form one request');
check($e[0]['blocked']===true,'block survives later Warning line');
check($e[0]['summary_only'] && $e[0]['detection_ids']===[],'949110 and 980130 are summaries, no invented root cause');
check($e[0]['uri']==='/livewire-006d7c12/update','actual blocked URL retained');
$e=Diagnostics::parse(lineFor(941100,'XSS Attack Detected via libinjection')."\n".$example);
check(count($e)===1 && $e[0]['detection_ids']===[941100] && !$e[0]['summary_only'],'earlier detection linked to block');
check(count($e[0]['rules'])===3,'all distinct rules retained');
$e=Diagnostics::parse(lineFor(941100,'XSS','a')."\n".lineFor(942100,'SQLi','b')."\n".lineFor(949110,'score','a',true));
check(count($e)===2 && $e[0]['unique_id']==='a' && $e[1]['unique_id']==='b','interleaved requests grouped independently');
$e=Diagnostics::parse(lineFor(941100,'message with \\"quoted\\" content'));
check($e[0]['rules'][0]['message']==='message with "quoted" content','escaped quotes parsed');
$e=Diagnostics::parse(lineFor(941100,'payload <script>alert(1)</script>','x',false,'/edit?token=secret'));
check($e[0]['uri']==='/edit','query string excluded from diagnostic URL');
check(!\IspModsecurity\Compat::contains(json_encode($e),'145.14.232.132'),'client IP not copied into result');
$without=preg_replace('/ \[unique_id "[^"]+"\]/','',lineFor(941100,'x'));
check(count(Diagnostics::parse($without."\n".$without))===2,'missing unique IDs are not merged');
check(Diagnostics::parse('PHP Fatal error: something')===[],'other errors ignored');
check(Diagnostics::parse('ModSecurity: malformed [id "oops"]')===[],'malformed lines ignored');
$text='';for($i=0;$i<150;$i++) $text.=lineFor(941100,'x','req-'.$i)."\n";
$e=Diagnostics::parse($text);
check(count($e)===100 && $e[0]['unique_id']==='req-149' && $e[99]['unique_id']==='req-50','only latest 100 requests retained');
foreach(['../other','a/../../etc','a..b','a"','/etc/passwd'] as $domain) {
 try { Diagnostics::logPath($domain); check(false,'unsafe domain rejected'); } catch(RuntimeException $ex) { check(true,'unsafe domain rejected'); }
}
$base=sys_get_temp_dir().'/modsec-diagnostic-'.bin2hex(random_bytes(6));mkdir($base);mkdir($base.'/example.cz');
file_put_contents($base.'/example.cz/error.log',$example);
check(Diagnostics::logPath('example.cz',$base)===$base.'/example.cz/error.log','canonical per-site path resolved');
unlink($base.'/example.cz/error.log');symlink('/etc/passwd',$base.'/example.cz/error.log');
try { Diagnostics::logPath('example.cz',$base); check(false,'symlink log rejected'); } catch(RuntimeException $ex) { check(true,'symlink log rejected'); }
unlink($base.'/example.cz/error.log');rmdir($base.'/example.cz');rmdir($base);
echo "$count diagnostics checks passed\n";
