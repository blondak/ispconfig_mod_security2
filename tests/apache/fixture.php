<?php
require '/work/modsecurity/lib/Config.php';
use IspModsecurity\Config;
$c=Config::defaults();
$c['mode']='On';$c['body_limit']=41943040;$c['nofiles_limit']=41943040;
$c['exceptions']=[['scope'=>'prefix','path'=>'/admin/','ids'=>[10001,10002,941100]],['scope'=>'exact','path'=>'/off','off'=>true],['scope'=>'exact','path'=>'/exact','ids'=>[10002]]];
file_put_contents('/etc/apache2/test-managed.conf',Config::render($c));

foreach(['Off','DetectionOnly'] as $mode) {
    $config=Config::defaults();$config['mode']=$mode;
    file_put_contents('/etc/apache2/test-'.$mode.'.conf',Config::render($config));
}
$config=Config::defaults();$config['exceptions']=[['scope'=>'site','ids'=>[10001,10002]]];
file_put_contents('/etc/apache2/test-site-exclusion.conf',Config::render($config));
