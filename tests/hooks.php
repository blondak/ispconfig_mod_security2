<?php
require __DIR__.'/../modsecurity/server/modsecurity_plugin.inc.php';
$p=new modsecurity_plugin();
foreach([1,42,2147483647] as $id) {
 $s=$p->template('', ['namespace'=>'apache2_vhost','name'=>'vhost_header','vars'=>['domain_id'=>$id]]);
 if($s!=="IncludeOptional /etc/apache2/ispconfig-modsecurity/web$id.conf\n") throw new RuntimeException('Invalid template hook');
}
if($p->template('', ['namespace'=>'nginx_vhost','name'=>'vhost_header'])!=='') throw new RuntimeException('Hook scope');
echo "4 hook tests passed\n";
