<?php
// Run with actual PHP below 7.4: never parse the supported runtime in this test.
if(PHP_VERSION_ID>=70400) { echo "SKIP: use PHP below 7.4 for the disabled-runtime regression\n";exit; }
$root=dirname(__DIR__).'/modsecurity';
$tmp=sys_get_temp_dir().'/modsecurity-disabled-'.bin2hex(random_bytes(5));
mkdir($tmp.'/lib/modsecurity',0755,true);mkdir($tmp.'/web/sites',0755,true);
foreach(['lib','lang'] as $dir)symlink($root.'/'.$dir,$tmp.'/lib/modsecurity/'.$dir);
symlink($root.'/interface/runtime',$tmp.'/lib/modsecurity/runtime');
define('ISPC_LIB_PATH',$tmp.'/lib');
$count=0;
function disabledCheck($ok,$message) { global $count;if(!$ok)throw new RuntimeException($message);echo 'PASS '.(++$count).' '.$message."\n"; }
$previousLog=ini_get('error_log');ini_set('error_log',$tmp.'/error.log');
try {
    foreach(['link','client','server'] as $name) {
        require $root.'/interface/modsecurity_'.$name.'_plugin.inc.php';
        $class='modsecurity_'.$name.'_plugin';$plugin=new $class();
        $plugin->onLoad();
        disabledCheck($plugin instanceof \IspModsecurity\UnavailablePlugin,'disabled panel plugin: '.$name);
        disabledCheck($plugin->login('login',[])==='','cached callbacks remain harmless: '.$name);
    }
    $module=['nav'=>[]];$_SESSION=['s'=>['user'=>['typ'=>'admin']]];
    foreach(['modsecurity_menu.php','modsecurity_admin_menu.php','modsecurity_limits.php'] as $file) {
        disabledCheck((require $root.'/interface/'.$file)===[],'provider disabled: '.$file);
    }
    disabledCheck($module['nav']===[],'no unusable menu entries registered');
    foreach(['plugin','module'] as $name) {
        require $root.'/server/modsecurity_'.$name.'.inc.php';
        $class='modsecurity_'.$name;$worker=new $class();$worker->onLoad();
        disabledCheck($worker instanceof \IspModsecurity\UnavailablePlugin,'disabled worker: '.$name);
    }
    file_put_contents($tmp.'/lib/config.inc.php',"<?php\n");
    file_put_contents($tmp.'/lib/app.inc.php',"<?php\n");
    $cwd=getcwd();chdir($tmp.'/web/sites');
    foreach(['modsecurity.php','modsecurity_diagnostics.php','modsecurity_server.php'] as $file) {
        ob_start();require $root.'/interface/'.$file;$output=ob_get_clean();
        disabledCheck(http_response_code()===503 && strpos($output,'PHP 7.4')!==false,'clear unavailable response: '.$file);
    }
    chdir($cwd);
    foreach(['en'=>'unavailable','de'=>'verfügbar','cz'=>'dostupné'] as $language=>$word) {
        $_SESSION['s']['language']=$language;
        disabledCheck(strpos(\IspModsecurity\Runtime::message(),$word)!==false,'localized runtime warning: '.$language);
    }
    disabledCheck(!class_exists('IspModsecurity\Config',false) && !class_exists('IspModsecurity\I18n',false),'supported runtime was never loaded');
    class extension_installer_base {}
    require $root.'/install/installer.php';
    foreach(['install','enable'] as $method) {
        $failed=false;try{(new modsecurity_installer())->$method();}catch(RuntimeException $e){$failed=strpos($e->getMessage(),'PHP 7.4')!==false;}
        disabledCheck($failed,'native installer rejects unsupported PHP: '.$method);
    }
    $log=file_get_contents($tmp.'/error.log');
    disabledCheck(substr_count($log,'ModSecurity extension disabled:')===1 && strpos($log,PHP_VERSION)!==false,'one diagnostic warning per request includes actual PHP version');
} finally {
    ini_set('error_log',$previousLog);
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) { if($file->isDir() && !$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname()); }
    rmdir($tmp);
}
echo "$count unsupported-runtime checks passed on PHP ".PHP_VERSION."\n";
