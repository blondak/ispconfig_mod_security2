<?php
// Root worker bootstrap; unsupported PHP must not parse the runtime.
require_once dirname(__DIR__).'/lib/Runtime.php';
if(!\IspModsecurity\Runtime::supported()) {
    class modsecurity_module extends \IspModsecurity\UnavailablePlugin {}
    return;
}
require_once __DIR__.'/runtime/modsecurity_module.inc.php';
