<?php
require __DIR__.'/../modsecurity/lib/Access.php';
use IspModsecurity\Access;
$app=new stdClass();
$app->db=new class {
 public $clients=[];
 public function queryOneRecord($sql,...$args) {
  if(str_contains($sql,'sys_group')) return ['client_id'=>1];
  return $this->clients[$args[0]]??null;
 }
};
function allowed($value,$message) { if(!$value)throw new RuntimeException($message);echo "PASS $message\n"; }
$_SESSION=['s'=>['user'=>['typ'=>'user','client_id'=>1]]];
$app->db->clients=[1=>['client_id'=>1,'parent_client_id'=>0,'limit_modsecurity'=>'n']];
allowed(!Access::clientMayManage(['sys_groupid'=>2]),'default client capability denied');
$app->db->clients[1]['limit_modsecurity']='y';
allowed(Access::clientMayManage(['sys_groupid'=>2]),'client capability enabled');
$app->db->clients[1]['parent_client_id']=2;$app->db->clients[2]=['client_id'=>2,'parent_client_id'=>0,'limit_modsecurity'=>'n'];
allowed(!Access::clientMayManage(['sys_groupid'=>2]),'reseller capability caps client');
$app->db->clients[2]['limit_modsecurity']='y';
allowed(Access::clientMayManage(['sys_groupid'=>2]),'enabled reseller and client');
$app->db->clients[1]['locked']='y';allowed(!Access::clientMayManage(['sys_groupid'=>2]),'locked client cannot change settings');unset($app->db->clients[1]['locked']);
$app->db->clients[2]['parent_client_id']=1;allowed(!Access::clientMayManage(['sys_groupid'=>2]),'reseller cycles fail closed');
$_SESSION['s']['user']['typ']='admin';allowed(Access::actorEnabled(),'admin actor bypass');
echo "7 access checks passed\n";
