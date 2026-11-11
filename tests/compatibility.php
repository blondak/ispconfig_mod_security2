<?php
// Exercise production compatibility on the minimum supported PHP 7.4.
require __DIR__.'/../modsecurity/lib/Compat.php';
require __DIR__.'/../modsecurity/lib/Runtime.php';
require __DIR__.'/../modsecurity/lib/Config.php';
require __DIR__.'/../modsecurity/lib/Apply.php';
require __DIR__.'/../modsecurity/lib/ServerConfig.php';
use IspModsecurity\Compat;
use IspModsecurity\Config;
use IspModsecurity\Apply;
use IspModsecurity\I18n;
$count=0;
function compatible($ok,$message) { global $count;if(!$ok)throw new RuntimeException($message);echo 'PASS '.(++$count).' '.$message."\n"; }
compatible(\IspModsecurity\Runtime::supported(),'minimum PHP runtime is enabled');
compatible(Compat::contains('abc','b') && Compat::contains('abc','') && !Compat::contains('abc','d'),'contains including empty needle');
compatible(Compat::startsWith('abc','ab') && Compat::startsWith('abc','') && !Compat::startsWith('abc','bc'),'prefix including empty needle');
compatible(Compat::endsWith('abc','bc') && Compat::endsWith('abc','') && !Compat::endsWith('abc','abcd'),'suffix including empty needle');
$config=Config::validate(['exceptions'=>[['scope'=>'regex','path'=>'^/livewire-[a-fA-F0-9]+/update$','ids'=>[941100]]]],[],true);
compatible(Compat::contains(Config::render($config),'<LocationMatch "^/livewire-[a-fA-F0-9]+/update$">'),'regex rendering on minimum PHP');
$dir=sys_get_temp_dir().'/modsec-compat-'.bin2hex(random_bytes(5));mkdir($dir);
try {
 Apply::run($dir.'/web1.conf','valid',function(){return true;},function(){return true;});
 compatible(file_get_contents($dir.'/web1.conf')==='valid','config application on minimum PHP');
}finally{foreach(glob($dir.'/*') as $file)unlink($file);@unlink($dir.'/.lock');rmdir($dir);}
foreach(['en'=>'Settings','de'=>'Einstellungen','cz'=>'Nastavení'] as $language=>$label) {
 $_SESSION['s']['language']=$language;compatible(I18n::t('settings')===$label,'translation on minimum PHP: '.$language);
}
echo "$count compatibility checks passed on PHP ".PHP_VERSION."\n";
