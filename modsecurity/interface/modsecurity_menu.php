<?php
require_once ISPC_LIB_PATH.'/modsecurity/lib/I18n.php';
// ISPConfig loads menu.d files in module scope. Pages use the native content pane.
$module['nav'][] = [
    'title' => 'ModSecurity', 'open' => 1,
    'items' => [
        ['title'=>'ModSecurity','target'=>'content','link'=>'sites/modsecurity.php','html_id'=>'modsecurity_settings'],
        ['title'=>\IspModsecurity\I18n::t('diagnostics_menu'),'target'=>'content','link'=>'sites/modsecurity_diagnostics.php','html_id'=>'modsecurity_diagnostics'],
    ],
];
