<?php
require __DIR__.'/../modsecurity/server/modsecurity_module.inc.php';
$app=new stdClass();$conf=['server_id'=>2];
$app->db=new class {
 public $queries=0;
 public function queryOneRecord($sql,...$params) { ++$this->queries; return null; }
};
$app->dbmaster=new class {
 public $errorNumber=0;
 public $calls=[];
 public function query($sql,...$params) { $this->calls[]=$params; }
};
$module=new modsecurity_module();
$row=['domain_id'=>176,'server_id'=>3,'revision'=>4];
$module->diagnostic('modsecurity_diagnostic_request','u',['new'=>$row]);
if($app->db->queries || $app->dbmaster->calls) throw new RuntimeException('Wrong server processed request');
$row['server_id']=2;
$module->diagnostic('modsecurity_diagnostic_request','u',['new'=>$row]);
$r=$app->dbmaster->calls[0];
if($r[0]!==176 || $r[1]!==2 || $r[2]!==4 || $r[3]!=='error' || json_decode($r[5],true)!==[]) throw new RuntimeException('Missing site must produce scoped error snapshot');
$app->dbmaster->errorNumber=1;
$thrown=false;
try { $module->diagnostic('modsecurity_diagnostic_request','u',['new'=>$row]); } catch(RuntimeException $e) { $thrown=true; }
if(!$thrown) throw new RuntimeException('Master write failure must remain retryable');
$before=count($app->dbmaster->calls);
$module->diagnostic('modsecurity_diagnostic_request','d',['old'=>$row]);
if(count($app->dbmaster->calls)!==$before) throw new RuntimeException('Deleted request should not collect logs');
echo "4 diagnostic routing/error checks passed\n";
