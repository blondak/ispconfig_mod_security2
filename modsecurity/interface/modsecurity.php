<?php
// Keep this entry point parseable before the PHP 7.4 runtime is loaded.
require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once ISPC_LIB_PATH.'/modsecurity/lib/Runtime.php';
if(!\IspModsecurity\Runtime::supported()) {
    http_response_code(503);
    echo htmlspecialchars(\IspModsecurity\Runtime::message(),ENT_QUOTES,'UTF-8');
    return;
}
return require ISPC_LIB_PATH.'/modsecurity/runtime/modsecurity.php';
