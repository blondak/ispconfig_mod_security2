<?php
$source=$argv[1]??'/tmp/ispconfig3_install';
$conf=['rootpath'=>$source.'/server'];
define('ISPC_CLASS_PATH',$source.'/server/lib/classes');
require $source.'/server/lib/classes/tpl.inc.php';
require __DIR__.'/../modsecurity/server/modsecurity_plugin.inc.php';
$app=new stdClass;
$app->plugins=new class {
 public function raiseAction($event,$data,$return) { return (new modsecurity_plugin)->template($event,$data); }
};
$path=tempnam(sys_get_temp_dir(),'ms-template-');
file_put_contents($path,"<tmpl_loop name='vhosts'><VirtualHost *:{tmpl_var name='port'}>\n<tmpl_hook name='apache2_vhost:vhost_header'>\n</VirtualHost>\n</tmpl_loop>");
try {
 $tpl=new tpl($path);$tpl->setVar('domain_id',42);$tpl->setLoop('vhosts',[['port'=>80],['port'=>443]]);$out=$tpl->grab();
 if(substr_count($out,'IncludeOptional /etc/apache2/ispconfig-modsecurity/web42.conf')!==2) throw new RuntimeException('HTTP/HTTPS template hook failed: '.$out);
 echo "Real ISPConfig template engine: HTTP + HTTPS hooks passed\n";
}finally{unlink($path);}
