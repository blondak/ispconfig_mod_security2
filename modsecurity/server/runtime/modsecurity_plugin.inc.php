<?php
require_once dirname(dirname(__DIR__)).'/lib/Config.php';
require_once dirname(dirname(__DIR__)).'/lib/Apply.php';
class modsecurity_plugin {
    public $plugin_name='modsecurity_plugin';
    public function onLoad() {
        global $app;
        $app->plugins->registerAction('on_template_content_hook',$this->plugin_name,'template');
        $app->plugins->registerEvent('web_domain_delete',$this->plugin_name,'deleted');
    }
    public function template($event,$data) {
        if(($data['namespace']??'')!=='apache2_vhost' || ($data['name']??'')!=='vhost_header') return '';
        $id=(int)($data['vars']['domain_id']??0);
        return $id>0 ? 'IncludeOptional /etc/apache2/ispconfig-modsecurity/web'.$id.'.conf'."\n" : '';
    }
    public function deleted($event,$data) {
        global $app,$conf;
        $id=(int)$data['old']['domain_id'];
        $app->db->query('DELETE FROM modsecurity_config WHERE domain_id = ? AND server_id = ?', $id,$conf['server_id']);
        $app->dbmaster->query('DELETE FROM modsecurity_config WHERE domain_id = ? AND server_id = ?', $id,$conf['server_id']);
        $app->dbmaster->query('DELETE FROM modsecurity_status WHERE domain_id = ? AND server_id = ?', $id,$conf['server_id']);
        $app->db->query('DELETE FROM modsecurity_diagnostic_request WHERE domain_id = ? AND server_id = ?', $id,$conf['server_id']);
        $app->dbmaster->query('DELETE FROM modsecurity_diagnostic_request WHERE domain_id = ? AND server_id = ?', $id,$conf['server_id']);
        $app->dbmaster->query('DELETE FROM modsecurity_diagnostic_result WHERE domain_id = ? AND server_id = ?', $id,$conf['server_id']);
        $path='/etc/apache2/ispconfig-modsecurity/web'.$id.'.conf';
        if(is_file($path)) unlink($path);
    }
}
