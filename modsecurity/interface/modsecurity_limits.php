<?php
require_once ISPC_LIB_PATH.'/modsecurity/lib/I18n.php';
return [
    'extension'=>'modsecurity',
    'min_core'=>'3.3.3p2',
    'section'=>['id'=>'ModSecurity','title'=>'ModSecurity','order'=>85],
    'limits'=>[
        'limit_modsecurity'=>[
            'label'=>\IspModsecurity\I18n::t('allow_management'),
            'datatype'=>'VARCHAR','formtype'=>'CHECKBOX','default'=>'n','value'=>[0=>'n',1=>'y'],
            'applies_to'=>['client','reseller','client_template'],
        ],
    ],
];
