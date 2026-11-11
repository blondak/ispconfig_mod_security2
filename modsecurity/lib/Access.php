<?php
namespace IspModsecurity;
final class Access {
    private static function chainEnabled(int $clientId): bool {
        global $app;
        $seen=[];
        while($clientId>0) {
            if(isset($seen[$clientId]) || count($seen)>=20) return false;
            $seen[$clientId]=true;
            $client=$app->db->queryOneRecord('SELECT client_id,parent_client_id,limit_modsecurity,locked,canceled FROM client WHERE client_id=?',$clientId);
            if(!$client || ($client['limit_modsecurity']??'n')!=='y' || ($client['locked']??'n')==='y' || ($client['canceled']??'n')==='y') return false;
            $clientId=(int)$client['parent_client_id'];
        }
        return (bool)$seen;
    }
    public static function actorEnabled(): bool {
        if(($_SESSION['s']['user']['typ']??'')==='admin') return true;
        return self::chainEnabled((int)($_SESSION['s']['user']['client_id']??0));
    }
    public static function ownerClientId(array $web): int {
        global $app;
        $group=$app->db->queryOneRecord('SELECT client_id FROM sys_group WHERE groupid=?',(int)$web['sys_groupid']);
        return (int)($group['client_id']??0);
    }
    /** Apply after the ordinary website ACL; this capability never grants website access. */
    public static function clientMayManage(array $web): bool {
        return self::chainEnabled(self::ownerClientId($web)) && self::actorEnabled();
    }
}
