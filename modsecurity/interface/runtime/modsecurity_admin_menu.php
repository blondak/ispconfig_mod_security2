<?php
if(($_SESSION['s']['user']['typ']??'')==='admin') $module['nav'][]=[
    'title'=>'ModSecurity','open'=>1,'items'=>[
        ['title'=>'ModSecurity','target'=>'content','link'=>'admin/modsecurity.php','html_id'=>'modsecurity_servers'],
    ],
];
