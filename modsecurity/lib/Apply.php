<?php
namespace IspModsecurity;
require_once __DIR__.'/I18n.php';
final class Apply {
    /** Caller serializes this with ISPConfig's server process; local lock protects retries. */
    public static function run(string $path, string $content, callable $check, callable $reload): void {
        self::many([$path=>$content],$check,$reload);
    }
    /** Apply server defaults and all affected vhost snippets with one configtest/reload. */
    public static function many(array $files, callable $check, callable $reload): void {
        if(!$files) return;
        $dir=dirname(array_key_first($files));
        if(!is_dir($dir) && !mkdir($dir,0750,true)) throw new \RuntimeException(I18n::t('config_directory'));
        $lock=fopen($dir.'/.lock','c');
        if(!$lock || !flock($lock,LOCK_EX)) throw new \RuntimeException(I18n::t('config_lock'));
        $old=[];
        foreach($files as $path=>$content) {
            if(dirname($path)!==$dir) { flock($lock,LOCK_UN);fclose($lock);throw new \InvalidArgumentException('Configuration files must share one directory.'); }
            $old[$path]=is_file($path)?file_get_contents($path):null;
        }
        try {
            foreach($files as $path=>$content) self::write($path,$content);
            if(!$check()) throw new \RuntimeException(I18n::t('apache_configtest'));
            if(!$reload()) throw new \RuntimeException(I18n::t('apache_reload'));
        } catch (\Throwable $e) {
            foreach($old as $path=>$content) {
                if($content===null) { if(is_file($path)) unlink($path); }
                else self::write($path,$content);
            }
            if(!$check() || !$reload()) throw new \RuntimeException($e->getMessage().I18n::t('apache_restore'),0,$e);
            throw $e;
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    private static function write(string $path,string $content): void {
        $tmp=tempnam(dirname($path),'.candidate-');
        if($tmp===false) throw new \RuntimeException(\IspModsecurity\I18n::t('temporary_file'));
        try {
            if(file_put_contents($tmp,$content)!==strlen($content) || !chmod($tmp,0640) || !rename($tmp,$path)) throw new \RuntimeException(\IspModsecurity\I18n::t('config_write'));
        } finally { if(is_file($tmp)) unlink($tmp); }
    }
}
