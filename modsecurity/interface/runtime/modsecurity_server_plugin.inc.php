<?php
class modsecurity_server_plugin {
    public function onLoad() {
        global $app;
        $app->plugin->registerEvent('admin:server_config:on_after_formdef','modsecurity_server_plugin','form');
        $app->plugin->registerEvent('admin:server_config:on_after_onShowEnd','modsecurity_server_plugin','show');
    }
    public function form($event,$tform) {
        if(($_SESSION['s']['user']['typ']??'')!=='admin' || (int)($_REQUEST['id']??0)<1) return;
        $tform->formDef['tabs']['modsecurity']=['title'=>'ModSecurity','width'=>100,'template'=>'../sites/templates/modsecurity_site_bridge.htm','fields'=>[]];
    }
    public function show($event,$page) {
        global $app;
        if($page->id>0 && $app->tform->getNextTab()==='modsecurity' && $app->tform->errorMessage==='') {
            echo 'HEADER_REDIRECT:admin/modsecurity.php?server_id='.(int)$page->id;
            exit;
        }
    }
}
