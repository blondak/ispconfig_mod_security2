<?php
namespace IspModsecurity;
final class I18n {
    private static $catalogs=[];
    public static function language(): string {
        global $conf;
        $language=strtolower(str_replace('_','-',(string)($_SESSION['s']['language']??$conf['language']??'en')));
        $language=explode('-',$language)[0];
        if($language==='cz') $language='cs';
        return in_array($language,['en','de','cs'],true)?$language:'en';
    }
    public static function catalog(?string $language=null): array {
        $language=$language??self::language();
        if(!isset(self::$catalogs[$language])) self::$catalogs[$language]=require __DIR__.'/../lang/'.$language.'.php';
        return self::$catalogs[$language];
    }
    public static function t(string $key): string {
        return self::catalog()[$key]??self::catalog('en')[$key]??$key;
    }
    /** Worker errors are stored in English; translate known messages when displayed. */
    public static function message(string $message): string {
        $map=[];
        foreach(['en','cs','de'] as $language) foreach(self::catalog($language) as $key=>$text) {
            if($message===$text) return self::t($key);
            if(strlen($text)>25) $map[$text]=self::t($key);
        }
        return strtr($message,$map);
    }
    public static function templateVars(): array {
        $vars=[];
        foreach(self::catalog('en') as $key=>$value) $vars['ms_'.$key]=self::t($key);
        return $vars;
    }
}
