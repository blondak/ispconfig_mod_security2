<?php
namespace IspModsecurity;
require_once __DIR__.'/I18n.php';
final class Store {
    /** Call while holding the website's named lock. The primary key is supplied by the caller. */
    public static function save($db, string $table, array $row, bool $exists): void {
        if(!in_array($table,['modsecurity_config','modsecurity_diagnostic_request','modsecurity_server_config'],true)) throw new \InvalidArgumentException(\IspModsecurity\I18n::t('invalid_table'));
        $primaryKey=$table==='modsecurity_server_config'?'server_id':'domain_id';
        if(!$db->query('START TRANSACTION')) throw new \RuntimeException(\IspModsecurity\I18n::t('transaction_start'));
        try {
            if($exists) {
                $db->datalogUpdate($table,$row,$primaryKey,$row[$primaryKey],true);
            } else {
                // ISPConfig datalogInsert uses LAST_INSERT_ID(), which can contain a previous
                // session/log insert ID for a non-auto-increment table. Pass our explicit PK.
                if(!$db->insertFromArray($table,$row)) throw new \RuntimeException(\IspModsecurity\I18n::t('settings_write'));
                $db->datalogSave($table,'INSERT',$primaryKey,$row[$primaryKey],[],$row);
            }
            if($db->errorNumber) throw new \RuntimeException(\IspModsecurity\I18n::t('datalog_write'));
            if(!$db->query('COMMIT')) throw new \RuntimeException(\IspModsecurity\I18n::t('commit_failed'));
        } catch(\Throwable $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
}
