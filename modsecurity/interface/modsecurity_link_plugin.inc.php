<?php
class modsecurity_link_plugin {
    public function onLoad() {
        global $app;
        $app->plugin->registerEvent('sites:web_vhost_domain:on_after_formdef','modsecurity_link_plugin','form');
        $app->plugin->registerEvent('sites:web_vhost_domain:on_after_onShowEnd','modsecurity_link_plugin','show');
        $app->plugin->registerEvent('login','modsecurity_link_plugin','login');
    }
    public function login($event,$data) {
        // The login controller builds its start module in local scope; menu.d uses
        // global scope in 3.3.3p2. Attach our menu to the saved start module as well.
        if(($_SESSION['s']['module']['name']??'')!=='sites') return;
        $module=$_SESSION['s']['module'];
        $module['nav']=array_values(array_filter($module['nav']??[],fn($nav)=>($nav['title']??'')!=='ModSecurity'));
        include '/usr/local/ispconfig/extensions/modsecurity/interface/modsecurity_menu.php';
        $_SESSION['s']['module']=$module;
    }
    public function link($event,$data) {
        // Sessions cache callbacks across deployments. Keep the former callback callable
        // and replace only this extension's registrations, preserving other plugins.
        foreach($_SESSION['s']['plugin_cache']??[] as $name=>$callbacks) {
            $callbacks=array_values(array_filter($callbacks,fn($callback)=>($callback['plugin']??'')!=='modsecurity_link_plugin'));
            if($callbacks) $_SESSION['s']['plugin_cache'][$name]=$callbacks;
            else unset($_SESSION['s']['plugin_cache'][$name]);
        }
        $this->onLoad();
        $this->login($event,$data);
        $id=(int)($_REQUEST['id']??0);
        // The tab header was rendered before this legacy end_form hook. Provide a
        // native link for this response; the following request uses the new tab hooks.
        return $id>0?'<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button type="button" class="btn btn-default formbutton-default" data-load-content="sites/modsecurity.php?domain_id='.$id.'">ModSecurity</button></div></div>':'';
    }
    public function form($event,$tform) {
        if((int)($_REQUEST['id']??$_REQUEST['domain_id']??0)<1) return;
        $tform->formDef['tabs']['modsecurity']=['title'=>'ModSecurity','width'=>100,'template'=>'templates/modsecurity_site_bridge.htm','fields'=>[]];
    }
    public function show($event,$page) {
        global $app;
        // Core tab changes first save/validate the current website tab. Only then enter
        // the extension controller. Its own buttons never POST to the website controller.
        if($page->id>0 && $app->tform->getNextTab()==='modsecurity' && $app->tform->errorMessage==='') {
            echo 'HEADER_REDIRECT:sites/modsecurity.php?domain_id='.(int)$page->id;
            exit;
        }
    }
}
