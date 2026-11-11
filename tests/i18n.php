<?php
require __DIR__.'/../modsecurity/lib/I18n.php';
require __DIR__.'/../modsecurity/lib/Config.php';
use IspModsecurity\I18n;
use IspModsecurity\Config;
$english=I18n::catalog('en');
foreach(['en','de','cs'] as $language) {
    $catalog=I18n::catalog($language);
    if(array_keys($catalog)!==array_keys($english) || in_array('',array_values($catalog),true)) throw new RuntimeException('Incomplete catalog: '.$language);
}
foreach(['en'=>'Settings','de'=>'Einstellungen','cz'=>'Nastavení','cs-CZ'=>'Nastavení','de_DE'=>'Einstellungen','fr'=>'Settings'] as $language=>$label) {
    $_SESSION['s']['language']=$language;
    if(I18n::t('settings')!==$label) throw new RuntimeException('Language selection: '.$language);
}
foreach(['en','de','cz'] as $language) {
    $_SESSION['s']['language']=$language;
    try { Config::validate(['mode'=>'invalid'],[],true); throw new RuntimeException('Validation did not reject'); }
    catch(InvalidArgumentException $e) { if($e->getMessage()!==I18n::t('invalid_mode'))throw $e; }
    if(I18n::message(I18n::catalog('en')['apache_configtest'])!==I18n::t('apache_configtest'))throw new RuntimeException('Worker error translation');
    if(I18n::message('Unrecognized technical error')!=='Unrecognized technical error')throw new RuntimeException('Unknown error altered');
}
foreach(glob(__DIR__.'/../modsecurity/interface/templates/*.htm') as $file) {
    preg_match_all('/tmpl_var name=[\'"](ms_[a-z_]+)[\'"]/',$html=file_get_contents($file),$matches);
    foreach($matches[1] as $name) if(!isset(I18n::templateVars()[$name]))throw new RuntimeException('Missing template translation '.$name);
    if(preg_match('/[ěščřžýáíéůúďťň]/u',$html))throw new RuntimeException('Hardcoded Czech in template '.$file);
}
echo count($english)." messages complete in EN/DE/CS; locale, validation, worker error and template checks passed\n";
