<?php
require __DIR__.'/../modsecurity/lib/Store.php';
use IspModsecurity\Store;
class FakeStoreDb {
 public $errorNumber=0; public $calls=[]; public $fail=false;
 public function query($sql) { $this->calls[]=$sql; return true; }
 public function insertFromArray($table,$row) { $this->calls[]=['insert',$table,$row];return true; }
 public function datalogSave($table,$action,$pk,$id,$old,$new) { $this->calls[]=['datalog',$pk,$id];if($this->fail)$this->errorNumber=1; }
 public function datalogUpdate($table,$row,$pk,$id,$force) { $this->calls[]=['update',$pk,$id,$force]; }
 public function datalogInsert(...$args) { throw new RuntimeException('Must not use LAST_INSERT_ID for explicit PK'); }
}
$db=new FakeStoreDb;Store::save($db,'modsecurity_config',['domain_id'=>176],false);
if($db->calls[0]!=='START TRANSACTION' || $db->calls[2]!==['datalog','domain_id',176] || end($db->calls)!=='COMMIT') throw new RuntimeException('Explicit primary key / atomic insert');
$db=new FakeStoreDb;Store::save($db,'modsecurity_diagnostic_request',['domain_id'=>176],true);
if($db->calls[1]!==['update','domain_id',176,true]) throw new RuntimeException('Force full snapshot on update');
$db=new FakeStoreDb;$db->fail=true;$failed=false;
try { Store::save($db,'modsecurity_config',['domain_id'=>176],false); } catch(RuntimeException $e) { $failed=true; }
if(!$failed || end($db->calls)!=='ROLLBACK') throw new RuntimeException('Queue failure must roll back settings');
echo "3 store regression checks passed\n";
