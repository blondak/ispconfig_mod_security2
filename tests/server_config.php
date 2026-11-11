<?php
require __DIR__.'/../modsecurity/lib/ServerConfig.php';
require __DIR__.'/../modsecurity/lib/Apply.php';
use IspModsecurity\ServerConfig;
use IspModsecurity\Apply;
$site=['mode'=>'On','body_limit'=>null,'nofiles_limit'=>null,'exceptions'=>[['scope'=>'exact','path'=>'/upload','off'=>true,'ids'=>[]],['scope'=>'site','ids'=>[941100],'off'=>false]]];
$server=ServerConfig::validate(['mode'=>'DetectionOnly','allow_override'=>false]);
$effective=ServerConfig::effective($site,$server);
if($effective['mode']!=='DetectionOnly' || count($effective['exceptions'])!==1 || $site['mode']!=='On')throw new RuntimeException('Forced server mode / preserve desired website values');
$server['allow_override']=true;
if(ServerConfig::effective($site,$server)!==$site)throw new RuntimeException('Website overrides');
$server['allow_override']=false;$server['mode']='inherit';
if(ServerConfig::effective($site,$server)!==$site)throw new RuntimeException('Inherit native Apache defaults');
$dir=sys_get_temp_dir().'/modsec-server-'.bin2hex(random_bytes(5));mkdir($dir);
file_put_contents($dir.'/server.conf','old server');file_put_contents($dir.'/web1.conf','old web');
$checks=0;$failed=false;
try { Apply::many([$dir.'/server.conf'=>'bad',$dir.'/web1.conf'=>'new', $dir.'/web2.conf'=>'new'],function()use(&$checks){return ++$checks>1;},fn()=>true); }
catch(RuntimeException $e) { $failed=true; }
if(!$failed || file_get_contents($dir.'/server.conf')!=='old server' || file_get_contents($dir.'/web1.conf')!=='old web' || is_file($dir.'/web2.conf'))throw new RuntimeException('Multi-file rollback');
foreach(glob($dir.'/*') as $file)unlink($file);unlink($dir.'/.lock');rmdir($dir);
echo "4 server policy / atomic rollback checks passed\n";
