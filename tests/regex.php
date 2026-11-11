<?php
require __DIR__.'/../modsecurity/lib/Config.php';
require __DIR__.'/../modsecurity/lib/ServerConfig.php';
use IspModsecurity\Config;
use IspModsecurity\ServerConfig;
$count=0;
function regexCheck(bool $ok,string $label): void { global $count;if(!$ok)throw new RuntimeException($label);echo 'PASS '.(++$count).' '.$label."\n"; }
$policy=['enabled'=>true,'allow_disable'=>true,'max_body_limit'=>41943040,'max_nofiles_limit'=>41943040];
$pattern='^/livewire-[a-fA-F0-9]+/update$';
$config=Config::validate(['exceptions'=>[['scope'=>'regex','path'=>$pattern,'ids'=>[941100,941130]]]],$policy);
regexCheck($config['exceptions'][0]['path']===$pattern,'regex is preserved in stored configuration');
regexCheck(str_contains(Config::render($config),'<LocationMatch "'.$pattern.'">'),'regex is not quoted as a literal path');
foreach(['/livewire-006d7c12/update'=>1,'/livewire-aF19/update'=>1,'/livewire-xyz/update'=>0,'/livewire-/update'=>0,'/livewire-ab/update/extra'=>0,'/prefix/livewire-ab/update'=>0] as $path=>$expected) {
    regexCheck(preg_match("\x01".$pattern."\x01",$path)===$expected,'Livewire matching '.$path);
}
$escaped='^/v\d+/item\.\d+$';
$c=Config::validate(['exceptions'=>[['scope'=>'regex','path'=>$escaped,'ids'=>[941100]]]],$policy);
regexCheck(str_contains(Config::render($c),str_replace('\\','\\\\',$escaped)),'backslashes are escaped for the Apache quoted argument');
foreach(['^/tag[#~][0-9]+$','^/(?:admin|api)/[^/]+$','^/a(?<number>[0-9]+)$'] as $valid) {
    Config::validate(['exceptions'=>[['scope'=>'regex','path'=>$valid,'ids'=>[941100]]]],$policy);regexCheck(true,'PCRE syntax accepted '.$valid);
}
foreach(['/livewire-.*','/^\/livewire-.*$/i','^/foo[','^/foo(','^/foo{4,2}$',"^/foo\\","^/x\"\nSecRuleEngine Off", "^/foo$\r\n", "^/foo bar$", "^/foo\x00bar", "^/foo\x01bar", '^/'.str_repeat('a',1023), '^/café$'] as $invalid) {
    $rejected=false;try { Config::validate(['exceptions'=>[['scope'=>'regex','path'=>$invalid,'ids'=>[941100]]]],$policy); }catch(InvalidArgumentException $e) { $rejected=true; }
    regexCheck($rejected,'invalid or unsafe regex rejected '.bin2hex(substr($invalid,0,28)));
}
Config::validate(['exceptions'=>[['scope'=>'regex','path'=>'^/'.str_repeat('a',1021).'$','ids'=>[941100]]]],$policy);
regexCheck(true,'1024-character regex limit is inclusive');
$off=Config::validate(['exceptions'=>[['scope'=>'regex','path'=>$pattern,'off'=>true]]],$policy);
regexCheck(str_contains(Config::render($off),'SecRuleEngine Off'),'regex can disable a URL when permitted');
$policy['allow_disable']=false;$rejected=false;
try { Config::validate($off,$policy); }catch(InvalidArgumentException $e) { $rejected=true; }
regexCheck($rejected,'regex URL disabling respects administrator policy');
$effective=ServerConfig::effective($off,ServerConfig::validate(['mode'=>'On','allow_override'=>false]));
regexCheck($effective['exceptions']===[],'forced server mode still removes regex URL disabling');
echo "$count regex checks passed\n";
