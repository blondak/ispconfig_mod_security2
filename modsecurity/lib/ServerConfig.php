<?php
namespace IspModsecurity;
require_once __DIR__.'/Config.php';
final class ServerConfig {
    public static function defaults(): array {
        return Config::defaults()+['allow_override'=>true];
    }
    public static function validate(array $config): array {
        $base=Config::validate(array_intersect_key($config,Config::defaults())+Config::defaults(),[],true);
        if($base['exceptions']) throw new \InvalidArgumentException('Server defaults cannot contain URL exceptions.');
        return $base+['allow_override'=>!empty($config['allow_override'])];
    }
    public static function forServer(int $serverId): array {
        global $app;
        $row=$app->db->queryOneRecord('SELECT config_json FROM modsecurity_server_config WHERE server_id=?',$serverId);
        return $row?self::validate(json_decode($row['config_json'],true,512,JSON_THROW_ON_ERROR)):self::defaults();
    }
    public static function locksMode(array $server): bool {
        return empty($server['allow_override']) && $server['mode']!=='inherit';
    }
    public static function effective(array $site,array $server): array {
        if(self::locksMode($server)) {
            $site['mode']=$server['mode'];
            $site['exceptions']=array_values(array_filter($site['exceptions'],fn($row)=>empty($row['off'])));
        }
        return $site;
    }
    public static function mib($value): ?int {
        if($value==='') return null;
        if(!is_scalar($value) || !preg_match('/^[0-9]+(?:\.[0-9]{1,6})?$/D',(string)$value)) throw new \InvalidArgumentException(I18n::t('invalid_mib'));
        return (int)round((float)$value*1048576);
    }
}
