<?php
namespace IspModsecurity;
/** Bootstrap only: keep compatible with PHP versions below the runtime minimum. */
final class Runtime {
    private static $reported=false;
    public static function supported() {
        if(PHP_VERSION_ID>=70400) return true;
        if(!self::$reported) {
            error_log('ModSecurity extension disabled: PHP 7.4 or newer is required; running PHP '.PHP_VERSION.'. ISPConfig remains available.');
            self::$reported=true;
        }
        return false;
    }
    public static function message() {
        global $conf;
        $language=isset($_SESSION['s']['language'])?$_SESSION['s']['language']:(isset($conf['language'])?$conf['language']:'en');
        $language=strtolower(substr($language,0,2));
        if($language==='cz') $language='cs';
        if(!in_array($language,array('en','de','cs'),true)) $language='en';
        $catalog=require dirname(__DIR__).'/lang/'.$language.'.php';
        return $catalog['unsupported_php'];
    }
}
/** ISPConfig instantiates plugins even when no events can be registered. */
class UnavailablePlugin {
    public function onLoad() {}
    // Existing sessions may still call callbacks registered before a PHP change.
    public function __call($method,$arguments) { return ''; }
}
