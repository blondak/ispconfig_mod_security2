<?php
namespace IspModsecurity;
/** Runtime helpers shared by the panel and worker on PHP 7.4 and PHP 8. */
final class Compat {
    public static function contains(string $value,string $needle): bool {
        return $needle==='' || strpos($value,$needle)!==false;
    }
    public static function startsWith(string $value,string $prefix): bool {
        return strncmp($value,$prefix,strlen($prefix))===0;
    }
    public static function endsWith(string $value,string $suffix): bool {
        return $suffix==='' || substr($value,-strlen($suffix))===$suffix;
    }
}
