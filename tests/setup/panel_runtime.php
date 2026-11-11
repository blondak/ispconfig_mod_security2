<?php
// Exercise full browser login and first panel requests with restricted open_basedir.
require __DIR__.'/http.php';
$vhost=file_get_contents('/etc/apache2/sites-available/ispconfig.vhost');
expect(str_contains($vhost,'php_admin_value open_basedir "/usr/local/ispconfig/interface:/usr/local/ispconfig/security:/usr/share:/tmp"'),'fixture enables production-like panel open_basedir');
$program='if (@is_readable("/usr/local/ispconfig/extensions/modsecurity/lib/I18n.php")) exit(1); require "/usr/local/ispconfig/interface/lib/modsecurity/lib/I18n.php"; if (\\IspModsecurity\\I18n::t("settings") !== "Settings") exit(2);';
$command=escapeshellarg(PHP_BINARY).' -d '.escapeshellarg('open_basedir=/usr/local/ispconfig/interface:/usr/local/ispconfig/security:/usr/share:/tmp').' -r '.escapeshellarg($program);
exec($command,$output,$code);
expect($code===0,'copied runtime loads while extension source is outside open_basedir');
$original=$db->query('SELECT startmodule FROM sys_user WHERE userid=1')->fetch_assoc()['startmodule'];
try {
    foreach(['dashboard','sites','admin'] as $module) {
        $q=$db->prepare('UPDATE sys_user SET startmodule=? WHERE userid=1');$q->bind_param('s',$module);$q->execute();
        $curl=curl_init();$jar=tempnam('/tmp','ms-panel-runtime-');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>10]);
        $request=function($path,$post=null)use($curl) {
            curl_setopt($curl,CURLOPT_URL,'http://127.0.0.1'.$path);curl_setopt($curl,CURLOPT_POST,$post!==null);
            if($post!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));
            $body=curl_exec($curl);if($body===false)throw new RuntimeException(curl_error($curl));
            return [curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$body];
        };
        try {
            [$code,$body]=$request('/login/');expect($code===200,'normal login page loads for '.$module);
            [$code,$body]=$request('/login/index.php',['username'=>'admin','password'=>'Admin-Lab-123!','s_mod'=>'login','s_pg'=>'index']);
            expect($code===302,'first non-AJAX login succeeds for '.$module);
            [$code,$body]=$request('/index.php');expect($code===200 && str_contains($body,'id="pageContent"'),'first full panel render succeeds for '.$module);
            [$code,$body]=$request('/capp.php?mod=admin');expect($code===200,'System switch succeeds for '.$module);
            [$code,$body]=$request('/admin/modsecurity.php');expect($code===200 && str_contains($body,'server1.lab.test') && str_contains($body,'server2.lab.test'),'server selector renders for '.$module);
            [$code,$body]=$request('/admin/modsecurity.php?server_id=1');expect($code===200 && str_contains($body,'name="mode"'),'server settings render for '.$module);
            [$code,$body]=$request('/capp.php?mod=sites');
            [$code,$body]=$request('/sites/modsecurity.php?domain_id=1');expect($code===200 && str_contains($body,'data-ms-settings'),'website settings render for '.$module);
        } finally { curl_close($curl);@unlink($jar); }
    }
} finally { $q=$db->prepare('UPDATE sys_user SET startmodule=? WHERE userid=1');$q->bind_param('s',$original);$q->execute(); }
echo "$count restricted panel login/render assertions passed\n";
