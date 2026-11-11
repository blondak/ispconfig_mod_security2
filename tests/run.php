<?php
require __DIR__.'/../modsecurity/lib/Config.php';
require __DIR__.'/../modsecurity/lib/Apply.php';
use IspModsecurity\Config;
use IspModsecurity\Apply;
$n=0;
function ok($value,$label) { global $n; ++$n; if(!$value) throw new RuntimeException($label); echo "ok $n - $label\n"; }
function rejects($fn,$label) { try{$fn();}catch(InvalidArgumentException $e){ok(true,$label);return;}ok(false,$label); }
$p=['enabled'=>true,'allow_disable'=>false,'max_body_limit'=>41943040,'max_nofiles_limit'=>41943040];
$base=Config::defaults();
ok(!str_contains(Config::render($base),'SecRuleEngine'),'default inherits');
$c=$base;$c['body_limit']=41943040;$c['nofiles_limit']=41943040;
ok(str_contains(Config::render($c),'SecRequestBodyNoFilesLimit 41943040'),'40 MiB limits');
rejects(fn()=>Config::validate(['mode'=>'Off'],$p),'client cannot disable');
rejects(fn()=>Config::validate(['mode'=>'DetectionOnly'],$p),'client cannot bypass with detection');
rejects(fn()=>Config::validate($base,[]),'client management disabled');
rejects(fn()=>Config::validate(['body_limit'=>41943041],$p),'admin maximum');
rejects(fn()=>Config::validate(['body_limit'=>20,'nofiles_limit'=>21],$p),'limit relation');
foreach(["/admin/\nSecRuleEngine Off",'/a"','/a?b=1','/a%2fb','/../a'] as $path) rejects(fn()=>Config::validate(['exceptions'=>[['scope'=>'exact','path'=>$path,'ids'=>[941100]]]],$p),'reject unsafe path');
rejects(fn()=>Config::validate(['exceptions'=>[['scope'=>'prefix','path'=>'/','off'=>true]]],$p),'URL disable cannot bypass policy');
rejects(fn()=>Config::validate(['exceptions'=>[['scope'=>'site','ids'=>['941100\nSecRuleEngine Off']]]],$p),'rule injection');
$c=$base;$c['exceptions']=[['scope'=>'exact','path'=>'/a.b','ids'=>[941100]],['scope'=>'prefix','path'=>'/admin/','ids'=>[942190]]];
$out=Config::render($c);
ok(str_contains($out,'"^/a\\.b$"'),'exact escaped anchored');
ok(str_contains($out,'"^/admin/"'),'prefix anchored');
$dir=sys_get_temp_dir().'/ispc-modsec-test-'.bin2hex(random_bytes(5));mkdir($dir);$path=$dir.'/web1.conf';file_put_contents($path,'old');
Apply::run($path,'new',fn()=>true,fn()=>true);ok(file_get_contents($path)==='new','successful apply');
$checks=0;$reloads=0;
try { Apply::run($path,'bad',function()use(&$checks){return ++$checks>1;},function()use(&$reloads){$reloads++;return true;}); } catch(RuntimeException $e) {}
ok(file_get_contents($path)==='new' && $reloads===1,'syntax failure restores old content');
$reloads=0;
try { Apply::run($path,'bad',fn()=>true,function()use(&$reloads){return ++$reloads>1;}); } catch(RuntimeException $e) {}
ok(file_get_contents($path)==='new' && $reloads===2,'reload failure restores and reloads old content');
unlink($path);unlink($dir.'/.lock');rmdir($dir);
echo "$n tests passed\n";
