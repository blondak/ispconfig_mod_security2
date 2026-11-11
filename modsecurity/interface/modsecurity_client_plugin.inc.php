<?php
require_once '/usr/local/ispconfig/extensions/modsecurity/lib/I18n.php';
require_once '/usr/local/ispconfig/extensions/modsecurity/lib/Access.php';
class modsecurity_client_plugin {
    public function onLoad() {
        global $app;
        foreach(['client','reseller','client_template'] as $form) {
            $app->plugin->registerEvent('client:'.$form.':on_after_onShowEnd','modsecurity_client_plugin','render');
            $app->plugin->registerEvent('client:'.$form.':on_before_update','modsecurity_client_plugin','beforeSave');
            $app->plugin->registerEvent('client:'.$form.':on_before_insert','modsecurity_client_plugin','beforeSave');
        }
    }
    public function render($event,$page) {
        global $app;
        if($app->tform->getNextTab()!=='limits') return;
        $form=$app->tform->formDef['name'];
        if(!in_array($form,['client','reseller','client_template'],true)) return;
        $app->tpl->setInclude('ms_original_limits','templates/'.$form.'_edit_limits.htm');
        $app->tpl->setInclude('content_tpl','../sites/templates/modsecurity_client_limits.htm');
    }
    public function beforeSave($event,$page) {
        global $app;
        if(($page->dataRecord['limit_modsecurity']??'n')==='y' && !\IspModsecurity\Access::actorEnabled()) {
            $app->tform->errorMessage.=\IspModsecurity\I18n::t('parent_denied').'<br />';
        }
    }
}
