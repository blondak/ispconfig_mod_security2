<?php
// Keep this entry point parseable before the PHP 7.4 runtime is loaded.
require_once ISPC_LIB_PATH.'/modsecurity/lib/Runtime.php';
if(!\IspModsecurity\Runtime::supported()) return array();
return require ISPC_LIB_PATH.'/modsecurity/runtime/modsecurity_menu.php';
